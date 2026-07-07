<?php

namespace Database\Seeders;

use App\Models\Atem;
use Illuminate\Database\Seeder;

class AddAtemTypeToExistingAtemsSeeder extends Seeder
{
    public function run(): void
    {
        Atem::withTrashed()
            ->whereNull('atem_type')
            ->update(['atem_type' => 1]);
    }
}
