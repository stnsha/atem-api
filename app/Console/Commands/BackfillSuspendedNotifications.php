<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-time data fix: the in-app 'atem_suspended' notification (AtemController::suspend())
 * was only added after some cards had already been suspended, so their Issuer never
 * received one - the card still shows as Suspended with no corresponding notification.
 * This backfills a notification for every currently-Suspended ATEM whose Issuer doesn't
 * already have one, mirroring the payload shape suspend() creates at suspend time.
 */
class BackfillSuspendedNotifications extends Command
{
    protected $signature = 'atem:backfill-suspended-notifications {--dry-run : List affected rows without creating notifications}';

    protected $description = 'Create the missing atem_suspended notification for already-suspended ATEM cards';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $suspended = Atem::with('status')
            ->whereHas('status', function ($q) {
                $q->where('value', 'Suspended');
            })
            ->get();

        $existingNotifiedAtemIds = AtemNotification::where('type', 'atem_suspended')
            ->whereIn('atem_id', $suspended->pluck('id'))
            ->pluck('atem_id')
            ->flip();

        $affected = $suspended->filter(function (Atem $atem) use ($existingNotifiedAtemIds) {
            $issuerId = (int) $atem->issuer_staff_id;
            $actorId  = (int) $atem->suspended_by;
            if ($issuerId <= 0 || $issuerId === $actorId) {
                return false;
            }
            return !$existingNotifiedAtemIds->has($atem->id);
        });

        if ($affected->isEmpty()) {
            $this->info('No suspended ATEM cards are missing a notification.');
            return 0;
        }

        foreach ($affected as $atem) {
            $this->line("ATEM #{$atem->id} ({$atem->title}) - issuer #{$atem->issuer_staff_id}, suspended by #{$atem->suspended_by}");
            if (!$dryRun) {
                AtemNotification::create([
                    'recipient_staff_id' => (int) $atem->issuer_staff_id,
                    'type'               => 'atem_suspended',
                    'atem_id'            => $atem->id,
                    'atem_message_id'    => null,
                    'payload'            => [
                        'atem_title'     => $atem->title,
                        'actor_staff_id' => (int) $atem->suspended_by,
                        'reason'         => Str::limit((string) $atem->suspended_remark, 120),
                    ],
                ]);
            }
        }

        $this->info(($dryRun ? '[Dry run] Would create ' : 'Created ') . $affected->count() . ' notification(s).');
        return 0;
    }
}
