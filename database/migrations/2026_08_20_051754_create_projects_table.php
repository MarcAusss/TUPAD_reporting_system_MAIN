<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('adl_allocation_id')
                ->constrained('adl_allocations')
                ->restrictOnDelete();

            $table->date('date_received');

            $table->string('project_title', 255);

            $table->text('nature_of_work');

            /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */

            $table->string('province', 150);
            $table->string('district', 100);
            $table->string('municipality', 150);
            $table->string('barangay', 150);

            /*
            |--------------------------------------------------------------------------
            | Income Class
            |--------------------------------------------------------------------------
            |
            | The official municipality-to-income-class reference dataset will be
            | introduced later. For now the selected value is stored on the project.
            |
            */

            $table->string('income_class', 50)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Implementation
            |--------------------------------------------------------------------------
            */

            $table->string('implementation_mode', 50);

            $table->unsignedSmallInteger('number_of_days');

            $table->string('term', 30);

            /*
            |--------------------------------------------------------------------------
            | Beneficiaries
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('beneficiaries_total');

            $table->unsignedInteger('beneficiaries_female')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Wage
            |--------------------------------------------------------------------------
            */

            $table->decimal('wage_rate', 12, 2);

            $table->decimal('wages_total', 15, 2);

            /*
            |--------------------------------------------------------------------------
            | PPE
            |--------------------------------------------------------------------------
            */

            $table->decimal('ppe_total', 15, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Insurance
            |--------------------------------------------------------------------------
            */

            $table->decimal('insurance_rate', 12, 2)
                ->default(50);

            $table->decimal('insurance_total', 15, 2)
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Overall Cost
            |--------------------------------------------------------------------------
            */

            $table->decimal('total_project_cost', 15, 2);

            /*
            |--------------------------------------------------------------------------
            | Workflow
            |--------------------------------------------------------------------------
            */

            $table->string('status', 50)
                ->default('ongoing_profiling');

            $table->text('remarks')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit Ownership
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('status');

            $table->index([
                'province',
                'municipality',
                'barangay',
            ]);

            $table->index('adl_allocation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};