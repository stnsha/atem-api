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
 *   - If no okr_key_results row points back to this atem, okr_key_result_id
 *     is set to NULL (see CLAUDE.md history: no reliable way to guess which
 *     of a card's many Key Results an orphaned ATEM belonged to).
 *   - If more than one okr_key_results row claims the same atem_id
 *     (claim_count > 1 from the lookup), the row is reported as a warning
 *     and left untouched - a separate data problem, not resolved here.
 */
class ReconcileOkrKeyResultLinks extends Command
{
    protected $signature = 'okr:reconcile-key-result-links
                            {--dry-run : Preview planned changes without writing to the database}
                            {--chunk=200 : ATEM ids sent per ODB lookup call}';

    protected $description = 'Correct or null out atems.okr_key_result_id against ODB okr_key_results.atem_id';

    public function handle(OdbOkrApiService $odb): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(1, (int) $this->option('chunk'));

        if ($dryRun) {
            $this->info('[dry-run] No changes will be written to the database.');
        }

        $rows = DB::table('atems')
            ->whereNotNull('okr_key_result_id')
            ->get(['id', 'okr_key_result_id']);

        if ($rows->isEmpty()) {
            $this->info('No atems with okr_key_result_id set. Nothing to do.');
            return Command::SUCCESS;
        }

        $this->info("Checking {$rows->count()} atems row(s) against ODB okr_key_results...");

        $corrected = 0;
        $nulled    = 0;
        $unchanged = 0;
        $warnings  = [];
        $changeLog = [];

        foreach ($rows->chunk($chunk) as $batch) {
            $atemIds = $batch->pluck('id')->all();
            $matches = $odb->lookupKeyResultsByAtemIds($atemIds);

            foreach ($batch as $row) {
                $atemId      = (int) $row->id;
                $currentValue = (int) $row->okr_key_result_id;
                $match        = $matches[$atemId] ?? null;

                if ($match && $match['claim_count'] > 1) {
                    $warnings[] = "ATEM #{$atemId}: {$match['claim_count']} Key Results all claim this ATEM "
                        . "(lowest: KR #{$match['key_result_id']}, card #{$match['card_id']}) - left untouched, needs manual review.";
                    continue;
                }

                $correctValue = $match ? $match['key_result_id'] : null;

                if ($correctValue === $currentValue) {
                    $unchanged++;
                    continue;
                }

                $changeLog[] = [
                    $atemId,
                    $currentValue,
                    $correctValue ?? 'NULL',
                    $correctValue ? 'corrected' : 'orphan -> null',
                ];

                if ($correctValue) {
                    $corrected++;
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
                ['Corrected (matched a Key Result)', $corrected],
                ['Nulled out (orphaned)', $nulled],
                ['Unchanged (already correct)', $unchanged],
                ['Flagged (multiple KRs claim same ATEM)', count($warnings)],
            ]
        );

        if ($dryRun) {
            $this->info('[dry-run] Re-run without --dry-run to apply these changes.');
        }

        return Command::SUCCESS;
    }
}
