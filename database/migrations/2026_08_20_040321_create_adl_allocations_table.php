<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adl_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('adl_id')
                ->constrained('adls')
                ->cascadeOnDelete();

            $table->string('fund_sponsor', 255);
            $table->string('partner', 255);
            $table->string('location', 255);

            $table->decimal('amount', 15, 2);

            $table->text('remarks')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('adl_id');
            $table->index('fund_sponsor');
            $table->index('partner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adl_allocations');
    }
};