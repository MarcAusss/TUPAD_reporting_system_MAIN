<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Region-wide beneficiary targets per fiscal year and term, entered by
     * Focal from the dashboard's Beneficiary Overview.
     */
    public function up(): void
    {
        Schema::create('beneficiary_targets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('term', 20);
            $table->unsignedInteger('target')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_targets');
    }
};
