<?php

namespace Database\Seeders;

use App\Models\LevelStructure;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LevelStructureSeeder extends Seeder
{
    private const CONDITIONS = [
        [
            'level' => 'Level 1',
            'system_name' => 'Operational ATEM',
            'incentive_value' => 0.00,
            'business_meaning' => 'Routine operational execution, simple improvement, follow-up or task-level enhancement.',
            'claim_treatment' => 'Completed and recorded, but no incentive payout.',
        ],
        [
            'level' => 'Level 2',
            'system_name' => 'Department / Managerial Improvement ATEM',
            'incentive_value' => 100.00,
            'business_meaning' => 'Improvement within one department or team, usually involving workflow, control, reporting or productivity enhancement.',
            'claim_treatment' => 'Incentive payable if closed as Complete or Complete with Excellence.',
        ],
        [
            'level' => 'Level 3',
            'system_name' => 'Cross-Functional / Strategic ATEM',
            'incentive_value' => 200.00,
            'business_meaning' => 'ATEM requiring more than one department, or linked to strategic business improvement, KPI / OKR, cost, revenue, risk or decision support.',
            'claim_treatment' => 'Incentive payable based on issuer-defined ARCI rule.',
        ],
        [
            'level' => 'Level 4',
            'system_name' => 'High Strategic / Company-Level ATEM',
            'incentive_value' => 300.00,
            'business_meaning' => 'High-impact ATEM linked to CEO priority, company-level governance, system design, major policy or scalable operating model change.',
            'claim_treatment' => 'Highest incentive level, payable based on issuer-defined ARCI rule.',
        ],
    ];

    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CONDITIONS as $condition) {
                LevelStructure::firstOrCreate(
                    ['level' => $condition['level']],
                    [
                        'system_name' => $condition['system_name'],
                        'incentive_value' => $condition['incentive_value'],
                        'business_meaning' => $condition['business_meaning'],
                        'claim_treatment' => $condition['claim_treatment'],
                    ]
                );
        }
    }
}