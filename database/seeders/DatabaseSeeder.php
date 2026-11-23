<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Default development seeding is disabled in production.');
            return;
        }

        $this->call(Fy2025TupadProjectSeeder::class);

        // Baseline municipal income classes (fills blanks only; Focal edits are kept).
        \App\Support\MunicipalityIncomeClasses::applyInitial();

        // Governors and Mayors of Bicol (fills blanks only; Focal edits are kept).
        $this->call(LocalChiefExecutiveSeeder::class, false, ['onlyBlanks' => true]);

        // $this->call(ProvinceProjectStatusCoverageSeeder::class);
    }
}
