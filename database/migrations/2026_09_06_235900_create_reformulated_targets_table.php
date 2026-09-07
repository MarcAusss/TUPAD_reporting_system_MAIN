<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reformulated_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')
                ->constrained('provinces')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('physical_target')->default(0);
            $table->unsignedBigInteger('financial_target_cents')->default(0);
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['province_id', 'fiscal_year'],
                'reformulated_targets_province_year_unique'
            );
            $table->index(
                ['fiscal_year', 'province_id'],
                'reformulated_targets_year_province_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reformulated_targets');
    }
};
