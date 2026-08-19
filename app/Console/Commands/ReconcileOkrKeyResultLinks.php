<?php

namespace App\Console\Commands;

use App\Services\OdbOkrApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles atems.okr_key_result_id against ODB's okr_key_results.atem_id
 * (the actual source of truth for the KR<->ATEM link).
 *
 * Run this AFTER the 2026_08_19_000001 rename migration. Before that
 * migration, existing values in this column are leftover OKR CARD ids from
 * when it was named okr_id - not Key Result ids - so every row needs
 * checking against ODB, not just newly-ambiguous ones:
 *
 *   - If exactly one okr_key_results row has atem_id = this atem's id,
 *     the atem's okr_key_result_id is corrected to that Key Result's real id.
 *   - If no okr_key_results row points back to this atem, a new Key Result
 *     is created under the OKR card the atem's (pre-rename) value pointed
 *     at, titled from the ATEM's own title and attributed to the ATEM's
 *     issuer - then okr_key_result_id is corrected to that new row. Falls
 *     back to NULL only if creation itself fails (e.g. the card no longer
 *     exists) - see CLAUDE.md history for why the *original* Key Result
 *     can't be recovered, only recreated as a new one.
 *   - If more than one okr_key_results row claims the same atem_id
 *     (claim_count > 1 from the lookup), the row is reported as a warning
 *     and left untouched - a separate data problem, not resolved here.
 */
class ReconcileOkrKeyResultLinks extends Command
{
    protected $signature = 'okr:reconcile-key-result-links
                            {--dry-run : Preview planned changes without writing to the database or creating Key Results on ODB}
                            {--chunk=200 : ATEM ids sent per ODB lookup call}';

    protected $description = 'Correct atems.okr_key_result_id against ODB okr_key_results.atem_id, creating a Key Result for orphans';

    // Key Results only ever take one of these four statuses (Draft/Extended/
    // Suspended/Deleted/Force Terminated are card-level or ATEM-only
    // concepts, not assignable to a Key Result row). ATEM values with no
    // direct KR equivalent collapse onto the closest one; anything not
    // listed here (including this map producing no entry) falls back to
    // "Active" in createOkrKeyResultForAtem.php itself.
    private const ATEM_TO_KR_STATUS = [
        'Active'                   => 'Active',
        'Extended'                 => 'Active',
        'Completed'                => 'Completed',
        'Completed with Extension' => 'Completed',
        'Completed with Excellence' => 'Completed with Excellence',
        'Failed'                   => 'Failed',
    ];

    public function handle(OdbOkrApiService $odb): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(1, (int) $this->option('chunk'));

        if ($dryRun) {
            $this->info('[dry-run] No changes will be written to the database, and no Key Results will be created on ODB.');
        }

        $rows = DB::table('atems')
            ->leftJoin('atem_statuses', 'atems.atem_status_id', '=', 'atem_statuses.id')
            ->whereNotNull('atems.okr_key_result_id')
            ->get([
                'atems.id', 'atems.okr_key_result_id', 'atems.title', 'atems.issuer_staff_id',
                'atems.start_date', 'atems.end_date',
                'atem_statuses.value as atem_status_value',
            ]);

        if ($rows->isEmpty()) {
            $this->info('No atems with okr_key_result_id set. Nothing to do.');
            return Command::SUCCESS;
        }

        $this->info("Checking {$rows->count()} atems row(s) against ODB okr_key_results...");

        $corrected = 0;
        $created   = 0;
        $nulled    = 0;
        $unchanged = 0;
        $warnings  = [];
        $changeLog = [];

        foreach ($rows->chunk($chunk) as $batch) {
            $atemIds = $batch->pluck('id')->all();
            $matches = $odb->lookupKeyResultsByAtemIds($atemIds);

            foreach ($batch as $row) {
                $atemId       = (int) $row->id;
                $currentValue = (int) $row->okr_key_result_id;
                $match        = $matches[$atemId] ?? null;

                if ($match && $match['claim_count'] > 1) {
                    $warnings[] = "ATEM #{$atemId}: {$match['claim_count']} Key Results all claim this ATEM "
                        . "(lowest: KR #{$match['key_result_id']}, card #{$match['card_id']}) - left untouched, needs manual review.";
                    continue;
                }

                if ($match) {
                    $correctValue = $match['key_result_id'];
                    $action       = 'corrected';
                } else {
                    // Orphan: currentValue is still the pre-rename OKR CARD id
                    // (the column only got renamed, values were never
                    // transformed) - that's the card to create the new Key
                    // Result under.
                    $cardId = $currentValue;

                    if ($dryRun) {
                        $correctValue = null;
                        $action       = "would create KR (card #{$cardId})";
                    } elseif ($cardId <= 0 || empty($row->issuer_staff_id)) {
                        $correctValue = null;
                        $action       = 'orphan -> null (missing card or issuer)';
                    } else {
                        $newKeyResultId = $odb->createKeyResultForAtem(
                            $cardId,
                            $row->title ?: ('ATEM #' . $atemId),
                            $atemId,
                            (int) $row->issuer_staff_id,
                            $row->start_date,
                            $row->end_date,
                            self::ATEM_TO_KR_STATUS[$row->atem_status_value] ?? null
                        );

                        if ($newKeyResultId) {
                            $correctValue = $newKeyResultId;
                            $action       = "created KR #{$newKeyResultId} (card #{$cardId})";
                        } else {
                            $correctValue = null;
                            $action       = "orphan -> null (KR creation failed, card #{$cardId})";
                        }
                    }
                }

                if ($correctValue === $currentValue) {
                    $unchanged++;
                    continue;
                }

                $changeLog[] = [
                    $atemId,
                    $currentValue,
                    $correctValue ?? 'NULL',
                    $action,
                ];

                if ($action === 'corrected') {
                    $corrected++;
                } elseif (str_starts_with($action, 'created') || str_starts_with($action, 'would create')) {
                    $created++;
                } else {
                    $nulled++;
                }

                if (!$dryRun) {
                    DB::table('atems')->where('id', $atemId)->update([
                        'okr_key_result_id' => $correctValue,
                    ]);
                }
            }
        }

        if (!empty($changeLog)) {
            $this->table(['ATEM ID', 'Old Value', 'New Value', 'Action'], $changeLog);
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Corrected (matched an existing Key Result)', $corrected],
                ['Created (new Key Result made for orphan)', $created],
                ['Nulled out (creation not possible)', $nulled],
                ['Unchanged (already correct)', $unchanged],
                ['Flagged (multiple KRs claim same ATEM)', count($warnings)],
            ]
        );

        if ($dryRun) {
            $this->info('[dry-run] Re-run without --dry-run to apply these changes (orphans will create real Key Results on ODB).');
        }

        return Command::SUCCESS;
    }
}
