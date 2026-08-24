<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,

                /*
                |--------------------------------------------------------------------------
                | Geographic references must exist before development projects.
                |--------------------------------------------------------------------------
                */

            LocationSeeder::class,

            DevelopmentDataSeeder::class,
            ProjectStatusHistorySeeder::class,
        ]);
    }
}