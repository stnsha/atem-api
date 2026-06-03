<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            AtemServiceSeeder::class,
            LevelStructureSeeder::class,
            IncentiveRuleSeeder::class,
            AtemStatusSeeder::class,
        ]);
    }
}