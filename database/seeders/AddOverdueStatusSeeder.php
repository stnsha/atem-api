<?php

namespace Database\Seeders;

use App\Models\AtemStatus;
use Illuminate\Database\Seeder;

class AddOverdueStatusSeeder extends Seeder
{
    public function run(): void
    {
        AtemStatus::firstOrCreate(
            ['value' => 'Overdue'],
            [
                'description'         => 'ATEM card has passed its target/due date without being resolved.',
                'system_action'       => 'Card is automatically flagged as overdue by the scheduler while still active.',
                'incentive_treatment' => 'Not eligible for incentive.',
            ]
        );
    }
}
