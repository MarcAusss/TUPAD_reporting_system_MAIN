<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn(
                'Default database seeding is disabled in production. Run only the specific reviewed reference-data or backfill seeder you intend to apply.'
            );

            return;
        }

        $this->call([
            UserSeeder::class,
            BicolLocationSeeder::class,
            CurrentSystemDemoSeeder::class,
            ProjectStatusHistorySeeder::class,
        ]);
    }
}
