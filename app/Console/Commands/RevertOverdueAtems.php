<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemAuditLog;
use App\Models\AtemStatus;
use App\Services\AtemAuditLogger;
use Illuminate\Console\Command;

/**
 * Reverts every ATEM card currently sitting at the "Overdue" status back to the
 * status it held immediately before it was auto-marked Overdue.
 *
 * The prior status is read from the newest atem_audit_logs row whose changes
 * array recorded a transition with to == "Overdue". When no such audit row
 * exists (or it recorded no usable "from" value) the card falls back to
 * "Active", which is the only status atem:mark-overdue ever transitioned from.
 *
 * This is the counterpart to the now-removed atem:mark-overdue command and is
 * safe to run repeatedly.
 */
class RevertOverdueAtems extends Command
{
    protected $signature = 'atem:revert-overdue {--dry-run : Show what would change without saving}';

    protected $description = 'Revert all ATEM cards currently marked Overdue back to their prior status';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $overdueStatusId = AtemStatus::where('value', 'Overdue')->value('id');

        if (!$overdueStatusId) {
            $this->info('No Overdue status exists in atem_statuses. Nothing to revert.');
            return 0;
        }

        $statusIdByValue = AtemStatus::pluck('id', 'value');
        $activeStatusId  = $statusIdByValue['Active'] ?? null;

        $atems = Atem::withTrashed()
            ->where('atem_status_id', $overdueStatusId)
            ->get();

        if ($atems->isEmpty()) {
            $this->info('No cards are currently Overdue. Nothing to revert.');
            return 0;
        }

        $reverted        = [];
        $skippedUnknown  = [];

        foreach ($atems as $atem) {
            $fromValue = $this->priorStatusValue($atem->id);

            if ($fromValue === null || $fromValue === '') {
                $fromValue = 'Active';
            }

            $targetId = $statusIdByValue[$fromValue] ?? $activeStatusId;

            if (!$targetId) {
                $skippedUnknown[] = $atem->id . ' (from "' . $fromValue . '")';
                continue;
            }

            $reverted[] = $atem->id . ' -> ' . $fromValue;

            if ($dryRun) {
                continue;
            }

            $atem->atem_status_id = (int) $targetId;
            $atem->saveQuietly();

            AtemAuditLogger::log(
                $atem->id,
                'status_changed',
                null,
                'Reverted from Overdue: restored to ' . $fromValue . ' after removal of the Overdue status.',
                [
                    ['field' => 'atem_status_id', 'label' => 'Status', 'from' => 'Overdue', 'to' => $fromValue],
                ]
            );
        }

        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->info($prefix . count($reverted) . ' card(s) reverted: ' . (implode(', ', $reverted) ?: '-'));

        if ($skippedUnknown) {
            $this->warn(count($skippedUnknown) . ' card(s) skipped, target status value not in atem_statuses: ' . implode(', ', $skippedUnknown));
        }

        return 0;
    }

    /**
     * Newest recorded status the card transitioned into Overdue from, or null.
     */
    private function priorStatusValue(int $atemId): ?string
    {
        $log = AtemAuditLog::where('atem_id', $atemId)
            ->where('event', 'status_changed')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->first(function (AtemAuditLog $row): bool {
                foreach ((array) $row->changes as $change) {
                    if (($change['field'] ?? null) === 'atem_status_id'
                        && ($change['to'] ?? null) === 'Overdue') {
                        return true;
                    }
                }
                return false;
            });

        if (!$log) {
            return null;
        }

        foreach ((array) $log->changes as $change) {
            if (($change['field'] ?? null) === 'atem_status_id'
                && ($change['to'] ?? null) === 'Overdue') {
                return $change['from'] ?? null;
            }
        }

        return null;
    }
}
