<?php

namespace Database\Seeders;

use App\Models\Plot;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a test Exhibitor user so you can log in easily
        User::factory()->create([
            'name' => 'Test Exhibitor',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'exhibitor',
        ]);

        // 2. Hardcode our ExpoMart Plots
        $plots = [
            'Premium-A1', 'Premium-A2', 'Premium-A3', 'Premium-A4',
            'Standard-B1', 'Standard-B2', 'Standard-B3', 'Standard-B4',
            'Economy-C1', 'Economy-C2', 'Economy-C3', 'Economy-C4',
        ];

        foreach ($plots as $plotName) {
            Plot::create([
                'name' => $plotName,
                'status' => 'available',
            ]);
        }
    }
}