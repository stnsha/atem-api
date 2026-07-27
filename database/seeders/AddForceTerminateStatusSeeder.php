<?php

namespace Database\Seeders;

use App\Models\AtemStatus;
use Illuminate\Database\Seeder;

class AddForceTerminateStatusSeeder extends Seeder
{
    public function run(): void
    {
        AtemStatus::firstOrCreate(
            ['value' => 'Force Terminated'],
            [
                'description'         => 'ATEM card remained suspended for more than 30 days without being resolved.',
                'system_action'       => 'Card is automatically closed by the scheduler, or manually closed by SuperAdmin, while suspended.',
                'incentive_treatment' => 'Not eligible for incentive.',
            ]
        );
    }
}