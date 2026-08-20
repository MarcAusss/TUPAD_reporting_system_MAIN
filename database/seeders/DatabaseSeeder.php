<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            [
                'username' => 'admin',
            ],
            [
                'name' => 'System Administrator',
                'email' => 'admin@tupad.local',
                'position' => 'System Administrator',
                'role' => UserRole::ADMIN,
                'is_active' => true,
                'supervisor_tc_id' => null,
                'password' => Hash::make('password'),
            ]
        );

        $tc = User::updateOrCreate(
            [
                'username' => 'tc',
            ],
            [
                'name' => 'TUPAD Coordinator',
                'email' => 'tc@tupad.local',
                'position' => 'TUPAD Coordinator',
                'role' => UserRole::TC,
                'is_active' => true,
                'supervisor_tc_id' => null,
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            [
                'username' => 'gip',
            ],
            [
                'name' => 'GIP Encoder',
                'email' => 'gip@tupad.local',
                'position' => 'GIP',
                'role' => UserRole::GIP,
                'is_active' => true,
                'supervisor_tc_id' => $tc->id,
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            [
                'username' => 'focal',
            ],
            [
                'name' => 'TUPAD Focal',
                'email' => 'focal@tupad.local',
                'position' => 'TUPAD Focal',
                'role' => UserRole::FOCAL,
                'is_active' => true,
                'supervisor_tc_id' => null,
                'password' => Hash::make('password'),
            ]
        );
    }
}