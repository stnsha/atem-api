<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemStatus;
use App\Services\AtemAuditLogger;
use Illuminate\Console\Command;

class AuditExtendedCompleted extends Command
{
    protected $signature = 'atem:audit-extended-completed';

    protected $description = 'Reclassify an ATEM card to Completed with Extension and set claimable = 0';

    public function handle(): int
    {
        $id = (int) $this->ask('Enter ATEM ID');

        if ($id <= 0) {
            $this->error('Invalid ATEM ID.');
            return 1;
        }

        $atem = Atem::with('status')->find($id);

        if (!$atem) {
            $this->error("ATEM ID {$id} not found.");
            return 1;
        }

        $newStatus = AtemStatus::where('value', 'Completed with Extension')->first();

        if (!$newStatus) {
            $this->error('"Completed with Extension" status not found. Run: php artisan db:seed --class=AddCompletedWithExtensionStatusSeeder');
            return 1;
        }

        $oldStatusValue = $atem->status ? $atem->status->value : '';

        $atem->atem_status_id = $newStatus->id;
        $atem->claimable      = false;
        $atem->saveQuietly();

        AtemAuditLogger::log(
            $atem->id,
            'status_changed',
            null,
            null,
            [
                ['field' => 'atem_status_id', 'label' => 'Status', 'from' => $oldStatusValue, 'to' => $newStatus->value],
            ]
        );

        $this->info("ATEM ID {$id} updated: status = \"{$newStatus->value}\", claimable = 0.");

        return 0;
    }
}
