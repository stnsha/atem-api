<?php

namespace Database\Seeders;

use App\Models\Pillar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PillarSeeder extends Seeder
{
    protected $pillars = [
        'Operation and Performance',
        'People Development & Team Coaching',
        'Customer Experience',
        'Special Project',
        'Market Business & Intelligences',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->pillars as $name) {
            Pillar::firstOrCreate(['name' => $name]);
        }
    }
}
