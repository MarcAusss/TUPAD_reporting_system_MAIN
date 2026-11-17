<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_ppe_deliveries', function (Blueprint $table): void {
            $table->string('delivery_receipt_number', 150)
                ->nullable()
                ->after('delivery_receipt_date');
        });
    }

    public function down(): void
    {
        Schema::table('project_ppe_deliveries', function (Blueprint $table): void {
            $table->dropColumn('delivery_receipt_number');
        });
    }
};
