<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AtemStatus;

class AtemStatusSeeder extends Seeder
{
    protected $data = [
        [
            'value' => 'Draft',
            'description' => 'ATEM saved as a draft; not yet submitted.',
            'system_action' => 'Card is editable and not counted as active work.',
            'incentive_treatment' => 'Not claimable yet.',
        ],
        [
            'value' => 'Active',
            'description' => 'ATEM is active and being worked on.',
            'system_action' => 'Card is open and editable.',
            'incentive_treatment' => 'Not claimable yet.',
        ],
        [
            'value' => 'Completed',
            'description' => 'ATEM deliverable completed and accepted by issuer.',
            'system_action' => 'Close card and lock closure status.',
            'incentive_treatment' => 'Eligible if Level 2-4 and ARCI rule applies.',
        ],
        [
            'value' => 'Completed with Excellence',
            'description' => 'ATEM completed with better-than-expected output or high management value.',
            'system_action' => 'Close card; tag as excellence for CEO / PPM reporting.',
            'incentive_treatment' => 'Eligible if Level 2-4 and ARCI rule applies.',
        ],
        [
            'value' => 'Extended',
            'description' => 'ATEM needs more time.',
            'system_action' => 'Issuer must key in extended date. Extension count increases by 1.',
            'incentive_treatment' => 'Not claimable yet.',
        ],
        [
            'value' => 'Failed',
            'description' => 'ATEM failed to deliver or no longer valid.',
            'system_action' => 'Close card as failed; require failure reason.',
            'incentive_treatment' => 'Not eligible for incentive',
        ],
    ];
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->data as $item) {
            AtemStatus::firstOrCreate([
                'value' => $item['value'],
            ], [
                'description' => $item['description'],
                'system_action' => $item['system_action'],
                'incentive_treatment' => $item['incentive_treatment'],
            ]);
        }
    }
}