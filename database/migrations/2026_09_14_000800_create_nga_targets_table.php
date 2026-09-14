<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-NGA/Partner target tracking. Distinct from reformulated_targets,
     * which tracks province-level physical/financial targets. This table is
     * a lightweight, manually-maintained target/accomplishment/balance
     * record per NGA (free text, reusing whatever Partner names are already
     * encoded on ADL Allocations/Projects).
     */
    public function up(): void
    {
        Schema::create('nga_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('nga', 255);
            $table->unsignedInteger('total_beneficiaries')->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedInteger('accomplishments')->default(0);
            $table->integer('balance')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('nga');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nga_targets');
    }
};
