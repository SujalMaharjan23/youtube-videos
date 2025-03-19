<?php

namespace Database\Seeders;

use App\Models\SourceTier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SourceTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SourceTier::insert([
            [
                'name' => 'High-Tiers',
                'slug' => 'HIGH',
                'interval_value' => 600
            ],
            [
                'name' => 'Mid-Tiers',
                'slug' => 'MID',
                'interval_value' => 1800
            ],
            [
                'name' => 'LOW-Tiers',
                'slug' => 'LOW',
                'interval_value' => 3600
            ]
        ]);        
    }
}
