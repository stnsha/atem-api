<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the "Overdue" status. Any ATEM card still pointing at it is moved back
 * to the status it held before atem:mark-overdue flipped it (read from
 * atem_audit_logs), falling back to "Active" — the only status that command ever
 * transitioned from. A reversing audit row is written for each moved card, then
 * the atem_statuses row itself is deleted.
 *
 * Self-contained: does not depend on the atem:revert-overdue command still
 * existing at replay time.
 */
return new class extends Migration
{
    public function up(): void
    {
        $overdueId = DB::table('atem_statuses')->where('value', 'Overdue')->value('id');

        if ($overdueId === null) {
            return;
        }

        $statusIdByValue = DB::table('atem_statuses')->pluck('id', 'value');
        $activeId        = $statusIdByValue['Active'] ?? null;

        $atemIds = DB::table('atems')->where('atem_status_id', $overdueId)->pluck('id');

        foreach ($atemIds as $atemId) {
            $fromValue = $this->priorStatusValue((int) $atemId);

            if ($fromValue === null || $fromValue === '') {
                $fromValue = 'Active';
            }

            $targetId = $statusIdByValue[$fromValue] ?? $activeId;

            if ($targetId === null) {
                continue;
            }

            DB::table('atems')
                ->where('id', $atemId)
                ->update(['atem_status_id' => (int) $targetId, 'updated_at' => now()]);

            DB::table('atem_audit_logs')->insert([
                'atem_id'        => $atemId,
                'event'          => 'status_changed',
                'actor_staff_id' => null,
                'summary'        => 'Reverted from Overdue: restored to ' . $fromValue . ' after removal of the Overdue status.',
                'changes'        => json_encode([
                    ['field' => 'atem_status_id', 'label' => 'Status', 'from' => 'Overdue', 'to' => $fromValue],
                ]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        DB::table('atem_statuses')->where('id', $overdueId)->delete();
    }

    public function down(): void
    {
        DB::table('atem_statuses')->updateOrInsert(
            ['value' => 'Overdue'],
            [
                'description'         => 'ATEM card has passed its target/due date without being resolved.',
                'system_action'       => 'Card is automatically flagged as overdue by the scheduler while still active.',
                'incentive_treatment' => 'Not eligible for incentive.',
                'updated_at'          => now(),
                'created_at'          => now(),
            ]
        );
    }

    /**
     * Newest status the card transitioned into Overdue from, per atem_audit_logs.
     */
    private function priorStatusValue(int $atemId): ?string
    {
        $rows = DB::table('atem_audit_logs')
            ->where('atem_id', $atemId)
            ->where('event', 'status_changed')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('changes');

        foreach ($rows as $raw) {
            $changes = json_decode((string) $raw, true) ?: [];

            foreach ($changes as $change) {
                if (($change['field'] ?? null) === 'atem_status_id'
                    && ($change['to'] ?? null) === 'Overdue') {
                    return $change['from'] ?? null;
                }
            }
        }

        return null;
    }
};
