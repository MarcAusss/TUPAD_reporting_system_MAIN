<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AlkanSSSya and the YAKAP Program for TUPAD Beneficiaries are mandatory
     * in every beneficiary orientation: existing orientations are marked as
     * covering both, and new ones default to it.
     */
    public function up(): void
    {
        DB::table('project_orientations')->update([
            'alkansssya_conducted' => true,
            'yakap_conducted' => true,
        ]);

        Schema::table('project_orientations', function (Blueprint $table): void {
            $table->boolean('alkansssya_conducted')->default(true)->change();
            $table->boolean('yakap_conducted')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_orientations', function (Blueprint $table): void {
            $table->boolean('alkansssya_conducted')->default(false)->change();
            $table->boolean('yakap_conducted')->default(false)->change();
        });
    }
};
