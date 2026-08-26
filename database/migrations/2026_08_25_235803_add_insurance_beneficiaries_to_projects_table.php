<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table
                ->unsignedInteger('insurance_beneficiaries')
                ->nullable()
                ->after('insurance_rate');
        });

        /*
        |--------------------------------------------------------------------------
        | Existing Projects
        |--------------------------------------------------------------------------
        |
        | Older projects assumed every project beneficiary required insurance.
        | Preserve those records by initially copying beneficiaries_total.
        |
        */

        DB::table('projects')
            ->whereNull('insurance_beneficiaries')
            ->update([
                'insurance_beneficiaries' =>
                    DB::raw('beneficiaries_total'),
            ]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(
                'insurance_beneficiaries'
            );
        });
    }
};