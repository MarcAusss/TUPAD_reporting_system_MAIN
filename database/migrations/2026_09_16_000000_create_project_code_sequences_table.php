<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counters backing the automatic TUPAD-RO5 Project Code series.
     *
     * One row per (province, year, month) scope. The series number is
     * reserved via an atomic upsert (see ProjectCodeGenerator) rather than
     * a count()+1 read, so concurrent approvals in the same scope can
     * never collide.
     */
    public function up(): void
    {
        Schema::create('project_code_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('last_series')->default(0);
            $table->timestamps();

            $table->unique(
                ['province_id', 'year', 'month'],
                'project_code_sequences_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_code_sequences');
    }
};
