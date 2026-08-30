<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('findings')->nullable();
            $table->text('required_documents')->nullable();
            $table->text('remarks')->nullable();
            $table->string('result', 30);
            $table->foreignId('evaluated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('evaluated_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->date('compliance_date')->nullable();
            $table->foreignId('complied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('complied_at')->nullable();

            $table->index(['project_id', 'evaluated_at'], 'project_evaluations_project_id_evaluated_at_index');
        });

        Schema::create('project_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('approval_date');
            $table->string('project_code', 100);
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_approvals_project_id_unique');
            $table->unique('project_code', 'project_approvals_project_code_unique');
        });

        Schema::create('project_insurance_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('date_enrolled');
            $table->unsignedInteger('beneficiary_count');
            $table->decimal('amount', 15, 2);
            $table->string('payment_mode', 30);
            $table->string('or_number', 150)->nullable();
            $table->string('policy_number', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_insurance_enrollments_project_id_unique');
        });

        Schema::create('project_ppe_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('delivery_receipt_date');
            $table->text('ppe_provided');
            $table->string('inventory_reference', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_ppe_deliveries_project_id_unique');
        });

        Schema::create('project_notice_to_proceeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('date_issued');
            $table->date('date_released');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_notice_to_proceeds_project_id_unique');
        });

        Schema::create('project_orientations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('orientation_date');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('alkansssya_conducted')->default(false);
            $table->boolean('yakap_conducted')->default(false);

            $table->unique('project_id', 'project_orientations_project_id_unique');
        });

        Schema::create('project_implementations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_implementations_project_id_unique');
            $table->index(['start_date', 'end_date'], 'project_implementations_start_date_end_date_index');
        });

        Schema::create('project_post_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('date_received');
            $table->string('document_type', 255);
            $table->string('attachment_path', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->date('date_forwarded_to_imsd')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['project_id', 'date_received'], 'project_post_documents_project_id_date_received_index');
        });

        Schema::create('project_obligations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('adl_number', 100);
            $table->string('fund_sponsor', 255);
            $table->string('partner', 255);
            $table->string('project_location', 500);
            $table->string('term', 50);
            $table->unsignedInteger('beneficiaries_total');
            $table->unsignedInteger('beneficiaries_female');
            $table->decimal('amount', 15, 2);
            $table->date('obligation_date');
            $table->string('month', 30);
            $table->string('payee', 255);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedTinyInteger('tranche_number')->default(1);

            $table->index('obligation_date', 'project_obligations_obligation_date_index');
            $table->index('month', 'project_obligations_month_index');
            $table->unique(['project_id', 'tranche_number'], 'project_obligations_project_tranche_unique');
        });

        Schema::create('project_disbursements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_obligation_id')->constrained('project_obligations')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date_disbursed');
            $table->string('ldap_check_number', 150);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('date_disbursed', 'project_disbursements_date_disbursed_index');
            $table->unique(['project_obligation_id', 'ldap_check_number'], 'project_disbursements_obligation_reference_unique');
        });

        Schema::create('project_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('payout_date');
            $table->string('payout_mode', 100);
            $table->string('venue', 255);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_payouts_project_id_unique');
            $table->index('payout_date', 'project_payouts_payout_date_index');
        });

        Schema::create('project_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('changed_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['project_id', 'changed_at'], 'project_status_histories_project_id_changed_at_index');
            $table->index('to_status', 'project_status_histories_to_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_status_histories');
        Schema::dropIfExists('project_payouts');
        Schema::dropIfExists('project_disbursements');
        Schema::dropIfExists('project_obligations');
        Schema::dropIfExists('project_post_documents');
        Schema::dropIfExists('project_implementations');
        Schema::dropIfExists('project_orientations');
        Schema::dropIfExists('project_notice_to_proceeds');
        Schema::dropIfExists('project_ppe_deliveries');
        Schema::dropIfExists('project_insurance_enrollments');
        Schema::dropIfExists('project_approvals');
        Schema::dropIfExists('project_evaluations');
    }
};
