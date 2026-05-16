<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (User::count() === 0) {
            User::create([
                'name' => 'Owner',
                'email' => 'owner@cut-tracker.local',
                'password' => bcrypt('changeme'),
            ]);
        }
    }
}
