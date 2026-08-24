<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('province_id')
                ->nullable()
                ->after('nature_of_work')
                ->constrained('provinces')
                ->nullOnDelete();

            $table->foreignId('municipality_id')
                ->nullable()
                ->after('province_id')
                ->constrained('municipalities')
                ->nullOnDelete();

            $table->foreignId('barangay_id')
                ->nullable()
                ->after('municipality_id')
                ->constrained('barangays')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId(
                'barangay_id'
            );

            $table->dropConstrainedForeignId(
                'municipality_id'
            );

            $table->dropConstrainedForeignId(
                'province_id'
            );
        });
    }
};