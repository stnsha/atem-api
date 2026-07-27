<?php

namespace App\Console\Commands;

use App\Models\Atem;
use Illuminate\Console\Command;

/**
 * One-time data fix: closure_date must only ever be set for genuinely closed
 * cards (Completed, Completed with Excellence, Completed with Extension, Failed
 * - matching AtemController::update()'s own $closingStatuses). Past bugs
 * (suspend setting closure_date to the suspend date; the Extended checkbox
 * setting it to the extension date while still non-terminal) left stale
 * closure_date values on rows that were never actually closed. Already-Deleted
 * (soft-deleted) cards are left untouched - historical records, not live data.
 */
class ResetInvalidClosureDates extends Command
{
    protected $signature = 'atem:reset-invalid-closure-dates {--dry-run : List affected rows without saving changes}';

    protected $description = 'Reset closure_date to null for any active ATEM not Completed/Completed with Excellence/Completed with Extension/Failed';

    private const VALID_CLOSURE_STATUSES = ['Completed', 'Completed with Excellence', 'Completed with Extension', 'Failed'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Default query already excludes soft-deleted (Deleted-status) rows.
        $affected = Atem::with('status')
            ->whereNotNull('closure_date')
            ->get()
            ->filter(function (Atem $atem) {
                $value = $atem->status ? $atem->status->value : null;
                return !in_array($value, self::VALID_CLOSURE_STATUSES, true);
            });

        if ($affected->isEmpty()) {
            $this->info('No ATEM cards have an invalid closure_date.');
            return 0;
        }

        foreach ($affected as $atem) {
            $statusValue = $atem->status ? $atem->status->value : 'Unknown';
            $this->line("ATEM #{$atem->id} ({$atem->title}) - status: {$statusValue}, closure_date: {$atem->closure_date}");
            if (!$dryRun) {
                $atem->closure_date = null;
                $atem->save();
            }
        }

        $this->info(($dryRun ? '[Dry run] Would reset ' : 'Reset ') . $affected->count() . ' ATEM card(s).');
        return 0;
    }
}
