<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemStatus;
use App\Services\AtemAuditLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkOverdueAtems extends Command
{
    protected $signature = 'atem:mark-overdue {--dry-run : List the ATEM cards that would be marked Overdue without saving changes}';

    protected $description = 'Mark Active ATEM cards as Overdue once their end date has passed';

    public function handle(): int
    {
        $overdueStatusId = AtemStatus::where('value', 'Overdue')->value('id');

        if (!$overdueStatusId) {
            $this->error('Overdue status not found in atem_statuses. Run the AddOverdueStatusSeeder first.');
            return 1;
        }

        $activeStatusId = AtemStatus::where('value', 'Active')->value('id');

        if (!$activeStatusId) {
            $this->info('No Active status found.');
            return 0;
        }

        $today = Carbon::today();

        $atems = Atem::where('atem_status_id', $activeStatusId)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->get();

        $dryRun = (bool) $this->option('dry-run');

        if ($atems->isEmpty()) {
            $this->info('No ATEM cards are overdue.');
            return 0;
        }

        $ids = $atems->pluck('id')->all();

        if ($dryRun) {
            $this->info("[dry-run] {$atems->count()} ATEM card(s) would be marked Overdue: " . implode(', ', $ids));
            return 0;
        }

        foreach ($atems as $atem) {
            $atem->atem_status_id = $overdueStatusId;
            $atem->saveQuietly();

            AtemAuditLogger::log(
                $atem->id,
                'status_changed',
                null,
                'Automatically marked Overdue: end date passed while status was Active.',
                [
                    ['field' => 'atem_status_id', 'label' => 'Status', 'from' => 'Active', 'to' => 'Overdue'],
                ]
            );
        }

        $this->info("Marked {$atems->count()} ATEM card(s) as Overdue: " . implode(', ', $ids));

        return 0;
    }
}
