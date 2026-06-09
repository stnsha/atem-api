<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JuneTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $months = [
            1 => ['name' => 'January',  'last_day' => 31],
            2 => ['name' => 'February', 'last_day' => 28],
            3 => ['name' => 'March',    'last_day' => 31],
            4 => ['name' => 'April',    'last_day' => 30],
            5 => ['name' => 'May',      'last_day' => 31],
            6 => ['name' => 'June',     'last_day' => 30],
        ];

        $STATUS_ACTIVE     = 2;
        $STATUS_COMPLETE   = 3;
        $STATUS_EXCELLENCE = 4;
        $STATUS_EXTENDED   = 5;
        $STATUS_FAILED     = 6;

        $closed = [$STATUS_COMPLETE, $STATUS_EXCELLENCE, $STATUS_FAILED];

        $records = [];

        foreach ($months as $monthNum => $monthData) {
            $name      = $monthData['name'];
            $lastDay   = $monthData['last_day'];
            $startDate = sprintf('2026-%02d-01', $monthNum);
            $endDate   = sprintf('2026-%02d-%02d', $monthNum, $lastDay);
            $createdAt = sprintf('2026-%02d-01 00:00:00', $monthNum);

            // Jan-Apr: L1 record 4 is Extended; May-Jun: Active
            $l1Item4Status = ($monthNum <= 4) ? $STATUS_EXTENDED : $STATUS_ACTIVE;

            // L1 — 5 cards, RM0 incentive
            $l1 = [
                ['status' => $STATUS_COMPLETE,  'claimable' => false, 'incentive' => 0.00],
                ['status' => $STATUS_COMPLETE,  'claimable' => false, 'incentive' => 0.00],
                ['status' => $STATUS_COMPLETE,  'claimable' => false, 'incentive' => 0.00],
                ['status' => $l1Item4Status,    'claimable' => false, 'incentive' => 0.00],
                ['status' => $STATUS_FAILED,    'claimable' => false, 'incentive' => 0.00],
            ];
            foreach ($l1 as $i => $t) {
                $records[] = $this->row("[L1] {$name} ATEM – " . ($i + 1), 1, null,   0.00, $t, $startDate, $endDate, $createdAt, $closed);
            }

            // L2 — 4 cards, RM100 base
            $l2 = [
                ['status' => $STATUS_COMPLETE,   'claimable' => true,  'incentive' => 100.00],
                ['status' => $STATUS_COMPLETE,   'claimable' => true,  'incentive' => 100.00],
                ['status' => $STATUS_EXCELLENCE, 'claimable' => true,  'incentive' => 100.00],
                ['status' => $STATUS_ACTIVE,     'claimable' => false, 'incentive' => 0.00],
            ];
            foreach ($l2 as $i => $t) {
                $records[] = $this->row("[L2] {$name} ATEM – " . ($i + 1), 2, 1, 100.00, $t, $startDate, $endDate, $createdAt, $closed);
            }

            // L3 — 4 cards, RM200 base
            $l3 = [
                ['status' => $STATUS_COMPLETE,   'claimable' => true,  'incentive' => 200.00],
                ['status' => $STATUS_COMPLETE,   'claimable' => true,  'incentive' => 200.00],
                ['status' => $STATUS_EXCELLENCE, 'claimable' => true,  'incentive' => 200.00],
                ['status' => $STATUS_FAILED,     'claimable' => false, 'incentive' => 0.00],
            ];
            foreach ($l3 as $i => $t) {
                $records[] = $this->row("[L3] {$name} ATEM – " . ($i + 1), 3, 1, 200.00, $t, $startDate, $endDate, $createdAt, $closed);
            }

            // L4 — 2 cards, RM300 base
            $l4 = [
                ['status' => $STATUS_COMPLETE,   'claimable' => true,  'incentive' => 300.00],
                ['status' => $STATUS_EXCELLENCE, 'claimable' => true,  'incentive' => 300.00],
            ];
            foreach ($l4 as $i => $t) {
                $records[] = $this->row("[L4] {$name} ATEM – " . ($i + 1), 4, 1, 300.00, $t, $startDate, $endDate, $createdAt, $closed);
            }
        }

        foreach (array_chunk($records, 50) as $chunk) {
            DB::table('atems')->insert($chunk);
        }

        $this->command->info('Inserted ' . count($records) . ' test ATEM records (Jan–Jun 2026).');
    }

    private function row(string $title, int $levelId, ?int $ruleId, float $base, array $t, string $startDate, string $endDate, string $createdAt, array $closed): array
    {
        $isClosed    = in_array($t['status'], $closed);
        $closureDate = $isClosed ? $endDate : null;

        return [
            'title'                  => $title,
            'description'            => null,
            'issuer_staff_id'        => null,
            'staff_dept_id'          => null,
            'level_structure_id'     => $levelId,
            'incentive_rule_id'      => $ruleId,
            'base_incentive'         => $base,
            'start_date'             => $startDate,
            'end_date'               => $endDate,
            'is_extended'            => $t['status'] === 5 ? 1 : 0,
            'extension_count'        => $t['status'] === 5 ? 1 : 0,
            'extended_date_1'        => $t['status'] === 5 ? $endDate : null,
            'extended_date_2'        => null,
            'final_due_date'         => $endDate,
            'closure_date'           => $closureDate,
            'atem_status_id'         => $t['status'],
            'remarks'                => null,
            'a_incentive_amount'     => $t['incentive'],
            'r_incentive_amount'     => 0.00,
            'total_incentive_amount' => $t['incentive'],
            'claimable'              => $t['claimable'] ? 1 : 0,
            'created_by'             => null,
            'updated_by'             => null,
            'closed_by'              => null,
            'deleted_at'             => null,
            'created_at'             => $createdAt,
            'updated_at'             => $createdAt,
        ];
    }
}
