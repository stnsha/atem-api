<?php

namespace Database\Seeders;

use App\Models\IncentiveRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncentiveRuleSeeder extends Seeder
{
    protected $rules = [
        [
            'code' => 'Rule 1',
            'system_label' => 'A1 50%, A2 50% incentivised.',
            'payout_logic' => 'A1 receives 50% of base incentive. A2 receives 50% of base incentive.',
        ],
        [
            'code' => 'Rule 2',
            'system_label' => 'Only A 100% incentivised.',
            'payout_logic' => 'A receives 100% of base incentive. R receives RM0.',
        ],
        [
            'code' => 'Rule 3',
            'system_label' => 'A 100%, R pooled 50% incentivised.',
            'payout_logic' => 'A receives 100% of base incentive. R ratings share a pooled 50% of base incentive equally.',
        ],
        [
            'code' => 'Rule 4',
            'system_label' => 'A1 50%, A2 50%, R pooled 50% incentivised.',
            'payout_logic' => 'A1 receives 50% of base incentive. A2 receives 50% of base incentive. R ratings share a pooled 50% of base incentive equally.',
        ],
    ];
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->rules as $rule) {
            IncentiveRule::firstOrCreate(
                ['code' => $rule['code']],
                [
                    'system_label' => $rule['system_label'],
                    'payout_logic' => $rule['payout_logic'],
                ]
            );
        }
    }
}