<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SpaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user2 = \App\Models\User::firstOrCreate(
            ['email' => 'tester@example.com'],
            [
                'name' => 'Tester User',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $space = \App\Models\Space::firstOrCreate(
            ['name' => 'My Bootcamp'],
            ['owner_id' => $user->id]
        );

        \App\Models\Room::create(['space_id' => $space->id, 'name' => 'Room A']);
        \App\Models\Room::create(['space_id' => $space->id, 'name' => 'Room B']);
        \App\Models\Room::create(['space_id' => $space->id, 'name' => 'Lobby']);
    }
}
