<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GSIS Group Personal Accident Insurance (GPAI) enrollment of TUPAD
     * beneficiaries, handled by the GSIS Enrollment Officer:
     *
     * - gpai_cash_advances: each Cash Advance (CA) drawn to pay CA-series
     *   enrollments (the "CA (Balance Calculation)" sheet).
     * - gpai_enrollments: one transmittal (bulk enrollment) numbered
     *   GPAI V26-0001 (voucher) or GPAI CA26-001 (cash advance), tracked
     *   through GSIS billing, payment, and OR / Policy issuance (the
     *   "2026 STATUS" sheet).
     * - gpai_enrollment_items: the projects in a transmittal. A project is
     *   enrolled once.
     */
    public function up(): void
    {
        Schema::create('gpai_cash_advances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->date('cash_advance_date');
            $table->decimal('amount', 15, 2);
            $table->string('reference', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['fiscal_year', 'cash_advance_date']);
        });

        Schema::create('gpai_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->string('gpai_number', 40)->unique();
            $table->string('payment_mode', 20);
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('sequence');
            $table->date('transmittal_date');
            $table->date('coverage_start');
            $table->date('coverage_end');
            $table->decimal('doc_stamp', 10, 2)->default(0);
            $table->foreignId('gpai_cash_advance_id')
                ->nullable()
                ->constrained('gpai_cash_advances')
                ->nullOnDelete();

            // GSIS processing steps (2026 STATUS sheet columns).
            $table->dateTime('billing_emailed_at')->nullable();
            $table->dateTime('soa_received_at')->nullable();
            $table->date('routing_released_on')->nullable();
            $table->date('payment_slip_on')->nullable();
            $table->dateTime('payment_slip_validated_at')->nullable();
            $table->dateTime('or_policy_requested_at')->nullable();
            $table->date('or_policy_received_on')->nullable();
            $table->date('distributed_on')->nullable();
            $table->string('or_number', 150)->nullable();
            $table->string('policy_id', 150)->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payment_mode', 'fiscal_year', 'sequence'], 'gpai_enrollments_series_unique');
            $table->index(['fiscal_year', 'transmittal_date']);
        });

        Schema::create('gpai_enrollment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gpai_enrollment_id')->constrained('gpai_enrollments')->cascadeOnDelete();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->string('adl_number', 100)->nullable();
            $table->string('particulars');
            $table->unsignedInteger('beneficiaries');
            $table->decimal('premium', 15, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpai_enrollment_items');
        Schema::dropIfExists('gpai_enrollments');
        Schema::dropIfExists('gpai_cash_advances');
    }
};
