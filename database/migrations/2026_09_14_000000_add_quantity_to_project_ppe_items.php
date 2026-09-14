<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_ppe_items', function (Blueprint $table): void {
            /*
            |--------------------------------------------------------------------------
            | PPE Quantity
            |--------------------------------------------------------------------------
            |
            | Units issued per beneficiary. Short-Term projects only issue one set per
            | beneficiary, so quantity stays 1. Long-Term projects may reissue PPE over
            | the project period, so Total = Beneficiaries x Unit Amount x Quantity.
            |
            */

            $table->unsignedInteger('quantity')
                ->default(1)
                ->after('beneficiary_count');
        });
    }

    public function down(): void
    {
        Schema::table('project_ppe_items', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
