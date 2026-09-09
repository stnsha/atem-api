<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemAuditLog;
use App\Models\AtemStatus;
use App\Services\AtemAuditLogger;
use Illuminate\Console\Command;

/**
 * One-time repair for the faulty atem:mark-overdue run that keyed off
 * final_due_date and swept in non-Active cards (Extended, etc.).
 *
 * For every card still sitting at Overdue, this looks up the audit row that
 * recorded its transition into Overdue. If it came from a status other than
 * Active, the card is restored to that prior status and a new audit row is
 * written. Cards that were genuinely Active before are left as Overdue.
 */
class RevertMistakenOverdue extends Command
{
    protected $signature = 'atem:revert-mistaken-overdue {--dry-run : Show what would change without saving}';

    protected $description = 'Revert ATEM cards wrongly marked Overdue (previous status was not Active) back to their prior status';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $overdueStatusId = AtemStatus::where('value', 'Overdue')->value('id');

        if (!$overdueStatusId) {
            $this->error('Overdue status not found in atem_statuses.');
            return 1;
        }

        $statusIdByValue = AtemStatus::pluck('id', 'value');

        $atems = Atem::withTrashed()
            ->where('atem_status_id', $overdueStatusId)
            ->get();

        if ($atems->isEmpty()) {
            $this->info('No cards are currently Overdue. Nothing to revert.');
            return 0;
        }

        $reverted = [];
        $keptActive = [];
        $skippedNoAudit = [];
        $skippedUnknown = [];

        foreach ($atems as $atem) {
            $log = AtemAuditLog::where('atem_id', $atem->id)
                ->where('event', 'status_changed')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->first(function (AtemAuditLog $row) {
                    foreach ((array) $row->changes as $change) {
                        if (($change['field'] ?? null) === 'atem_status_id'
                            && ($change['to'] ?? null) === 'Overdue') {
                            return true;
                        }
                    }
                    return false;
                });

            if (!$log) {
                $skippedNoAudit[] = $atem->id;
                continue;
            }

            $fromValue = null;
            foreach ((array) $log->changes as $change) {
                if (($change['field'] ?? null) === 'atem_status_id' && ($change['to'] ?? null) === 'Overdue') {
                    $fromValue = $change['from'] ?? null;
                    break;
                }
            }

            if ($fromValue === 'Active' || $fromValue === null || $fromValue === '') {
                $keptActive[] = $atem->id;
                continue;
            }

            if (!isset($statusIdByValue[$fromValue])) {
                $skippedUnknown[] = $atem->id . ' (from "' . $fromValue . '")';
                continue;
            }

            $reverted[] = $atem->id . ' -> ' . $fromValue;

            if ($dryRun) {
                continue;
            }

            $atem->atem_status_id = (int) $statusIdByValue[$fromValue];
            $atem->saveQuietly();

            AtemAuditLogger::log(
                $atem->id,
                'status_changed',
                null,
                'Reverted from Overdue: card was ' . $fromValue . ' before the faulty auto-overdue run and was never Active.',
                [
                    ['field' => 'atem_status_id', 'label' => 'Status', 'from' => 'Overdue', 'to' => $fromValue],
                ]
            );
        }

        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->info($prefix . count($reverted) . ' card(s) reverted: ' . (implode(', ', $reverted) ?: '-'));
        $this->info($prefix . count($keptActive) . ' card(s) kept Overdue (was Active): ' . (implode(', ', $keptActive) ?: '-'));

        if ($skippedNoAudit) {
            $this->warn(count($skippedNoAudit) . ' card(s) skipped, no Overdue audit row found: ' . implode(', ', $skippedNoAudit));
        }

        if ($skippedUnknown) {
            $this->warn(count($skippedUnknown) . ' card(s) skipped, prior status value not in atem_statuses: ' . implode(', ', $skippedUnknown));
        }

        return 0;
    }
}
