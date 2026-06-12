<?php

namespace App\Observers;

use App\Models\Atem;
use App\Models\AtemStatus;
use App\Models\IncentiveRule;
use App\Models\LevelStructure;
use App\Services\AtemAuditLogger;

class AtemObserver
{
    private static array $pending = [];

    private const TRACKED = [
        'title'              => 'ATEM Title',
        'description'        => 'Description',
        'level_structure_id' => 'ATEM Level',
        'incentive_rule_id'  => 'Incentive Rule',
        'start_date'         => 'Start Date',
        'end_date'           => 'End Date',
        'is_extended'        => 'Extended',
        'extended_date_1'    => 'Extended Date',
        'final_incentive_amount' => 'Final Incentive Amount',
        'incentive_approved' => 'Incentive Approved',
        'atem_status_id'     => 'Status',
        'remarks'            => 'Remarks',
    ];

    public function created(Atem $atem): void
    {
        AtemAuditLogger::log($atem->id, 'created', $atem->created_by, 'ATEM created.');
    }

    public function updating(Atem $atem): void
    {
        $dirty    = $atem->getDirty();
        $changes  = [];

        foreach (self::TRACKED as $field => $label) {
            if (!array_key_exists($field, $dirty)) {
                continue;
            }
            $from = $this->display($field, $atem->getOriginal($field));
            $to   = $this->display($field, $dirty[$field]);
            if ($from === $to) {
                continue;
            }
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $from,
                'to'    => $to,
            ];
        }

        if (!empty($changes)) {
            self::$pending[$atem->id] = [
                'changes' => $changes,
                'actor'   => $dirty['updated_by'] ?? $atem->updated_by,
            ];
        }
    }

    public function updated(Atem $atem): void
    {
        if (!isset(self::$pending[$atem->id])) {
            return;
        }

        $pending = self::$pending[$atem->id];
        unset(self::$pending[$atem->id]);

        $hasStatus = (bool) array_filter(
            $pending['changes'],
            static fn($c) => $c['field'] === 'atem_status_id'
        );

        AtemAuditLogger::log(
            $atem->id,
            $hasStatus ? 'status_changed' : 'updated',
            $pending['actor'],
            null,
            $pending['changes']
        );
    }

    private function display(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($field === 'level_structure_id') {
            $level = LevelStructure::find((int) $value);
            return $level ? ('Level ' . $level->level . ' - ' . $level->system_name) : (string) $value;
        }

        if ($field === 'incentive_rule_id') {
            $rule = IncentiveRule::find((int) $value);
            return $rule ? ($rule->code . ' - ' . $rule->system_label) : (string) $value;
        }

        if ($field === 'atem_status_id') {
            $status = AtemStatus::find((int) $value);
            return $status ? $status->value : (string) $value;
        }

        if ($field === 'is_extended') {
            return $value ? 'Yes' : 'No';
        }

        if ($field === 'description') {
            return '[updated]';
        }

        return (string) $value;
    }
}
