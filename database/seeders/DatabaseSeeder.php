<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LocationSeeder::class,
            BicolLocationSeeder::class,

            DevelopmentDataSeeder::class,
            ProjectStatusHistorySeeder::class,
            ProvincialProjectSeeder::class,
        ]);
    }
}