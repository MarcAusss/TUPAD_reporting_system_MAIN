<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_drafts', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Assignment
            |--------------------------------------------------------------------------
            */

            $table->foreignId('encoded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('assigned_tc_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ADL Allocation
            |--------------------------------------------------------------------------
            */

            $table->foreignId('adl_allocation_id')
                ->constrained('adl_allocations')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | General Project Information
            |--------------------------------------------------------------------------
            */

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

            $table->string('income_class', 50)
                ->nullable();

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
            | Overall Project Cost
            |--------------------------------------------------------------------------
            */

            $table->decimal('total_project_cost', 15, 2);

            /*
            |--------------------------------------------------------------------------
            | Draft Workflow
            |--------------------------------------------------------------------------
            */

            $table->string('status', 50)
                ->default('draft');

            $table->text('remarks')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | TC Review
            |--------------------------------------------------------------------------
            */

            $table->text('tc_review_remarks')
                ->nullable();

            $table->timestamp('submitted_at')
                ->nullable();

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Official Project Link
            |--------------------------------------------------------------------------
            */

            $table->foreignId('confirmed_project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            $table->timestamp('confirmed_at')
                ->nullable();

            $table->timestamps();

            $table->index('status');

            $table->index([
                'assigned_tc_id',
                'status',
            ]);

            $table->index([
                'encoded_by',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_drafts');
    }
};