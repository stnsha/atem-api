<?php

namespace Database\Seeders;

use App\Models\AtemStatus;
use Illuminate\Database\Seeder;

class AddCompletedWithExtensionStatusSeeder extends Seeder
{
    public function run(): void
    {
        AtemStatus::firstOrCreate(
            ['value' => 'Completed with Extension'],
            [
                'description'         => 'ATEM completed after one or more extensions were granted.',
                'system_action'       => 'Close card; tag as completed with extension for reporting.',
                'incentive_treatment' => 'Eligible if Level 2-4 and ARCI rule applies.',
            ]
        );
    }
}
