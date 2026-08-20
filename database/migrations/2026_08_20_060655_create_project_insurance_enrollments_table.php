<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_insurance_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->unique()
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->date('date_enrolled');

            $table->unsignedInteger('beneficiary_count');

            $table->decimal('amount', 15, 2);

            $table->string('payment_mode', 30);

            $table->string('or_number', 150)->nullable();

            $table->string('policy_number', 150)->nullable();

            $table->text('remarks')->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_insurance_enrollments');
    }
};