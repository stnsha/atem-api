<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkOverdueAtems extends Command
{
    protected $signature = 'atem:mark-overdue {--dry-run : List the ATEM cards that would be marked Overdue without saving changes}';

    protected $description = 'Mark Active/Extended ATEM cards as Overdue once their due date has passed';

    public function handle(): int
    {
        $overdueStatusId = AtemStatus::where('value', 'Overdue')->value('id');

        if (!$overdueStatusId) {
            $this->error('Overdue status not found in atem_statuses. Run the AddOverdueStatusSeeder first.');
            return 1;
        }

        $openStatusIds = AtemStatus::whereIn('value', ['Active', 'Extended'])->pluck('id');

        if ($openStatusIds->isEmpty()) {
            $this->info('No Active/Extended statuses found.');
            return 0;
        }

        $today = Carbon::today();

        $atems = Atem::whereIn('atem_status_id', $openStatusIds)
            ->whereNotNull('final_due_date')
            ->whereDate('final_due_date', '<', $today)
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
            $atem->save();
        }

        $this->info("Marked {$atems->count()} ATEM card(s) as Overdue: " . implode(', ', $ids));

        return 0;
    }
}
