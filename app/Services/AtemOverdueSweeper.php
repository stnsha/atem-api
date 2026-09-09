<?php

namespace App\Services;

use App\Models\Atem;
use App\Models\AtemStatus;

class AtemOverdueSweeper
{
    /**
     * Flips any Active/Extended card whose final_due_date has already
     * passed to Overdue. This app has no scheduler wired up (Kernel::schedule()
     * is empty and there's no confirmed cron running `schedule:run`), so a
     * daily background job can't be relied on - instead this runs as a
     * single, cheap UPDATE query called at the top of every list/detail read
     * (AtemController::index()/show()), keeping atem_status_id correct on
     * demand without depending on server-side cron.
     *
     * final_due_date already mirrors extended_date_1 when the card is
     * extended, or end_date otherwise (see AtemController::update()), so no
     * extension-aware date logic needs to be duplicated here.
     */
    public static function sync(): void
    {
        $overdueId = AtemStatus::where('value', 'Overdue')->whereNull('deleted_at')->value('id');
        if (!$overdueId) {
            // Not seeded in this environment yet - nothing to flip to.
            return;
        }

        $openIds = AtemStatus::whereIn('value', ['Active', 'Extended'])->whereNull('deleted_at')->pluck('id');
        if ($openIds->isEmpty()) {
            return;
        }

        Atem::whereIn('atem_status_id', $openIds)
            ->whereNull('deleted_at')
            ->whereNotNull('final_due_date')
            ->where('final_due_date', '<', now()->toDateString())
            ->update(['atem_status_id' => $overdueId]);
    }
}
