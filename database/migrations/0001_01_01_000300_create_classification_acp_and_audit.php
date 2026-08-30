<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_beneficiary_sectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('sector_group', 50);
            $table->string('sector_key', 100);
            $table->unsignedInteger('beneficiaries_total')->default(0);
            $table->unsignedInteger('beneficiaries_female')->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['project_id', 'sector_key'], 'project_beneficiary_sectors_project_key_unique');
            $table->index(['sector_group', 'sector_key'], 'project_beneficiary_sectors_reporting_index');
        });

        Schema::create('project_labor_market_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('reporting_month');
            $table->string('program', 100);
            $table->unsignedInteger('interested_referred_total')->default(0);
            $table->unsignedInteger('interested_referred_female')->default(0);
            $table->unsignedInteger('provided_intervention_total')->default(0);
            $table->unsignedInteger('provided_intervention_female')->default(0);
            $table->decimal('amount_released', 15, 2)->default(0);
            $table->text('services_availed');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['project_id', 'reporting_month', 'program'], 'project_labor_referrals_month_program_unique');
            $table->index(['reporting_month', 'program'], 'project_labor_referrals_reporting_index');
        });

        Schema::create('project_acp_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payee', 255);
            $table->string('payment_reference', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_acp_payments_project_id_unique');
            $table->index('payment_date', 'project_acp_payments_payment_date_index');
        });

        Schema::create('project_acp_check_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('check_number', 150);
            $table->date('check_date');
            $table->decimal('amount', 15, 2);
            $table->date('released_date');
            $table->string('released_to', 255);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_acp_check_releases_project_id_unique');
            $table->unique('check_number', 'project_acp_check_releases_check_number_unique');
            $table->index('released_date', 'project_acp_check_releases_released_date_index');
        });

        Schema::create('project_acp_check_release_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_acp_check_release_id');
            $table->string('original_name', 255);
            $table->string('attachment_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('project_acp_check_release_id', 'acp_release_attach_release_fk')->references('id')->on('project_acp_check_releases')->cascadeOnDelete();
        });

        Schema::create('project_acp_liquidations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('liquidation_date');
            $table->decimal('amount', 15, 2);
            $table->string('liquidation_reference', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['project_id', 'liquidation_date'], 'acp_liq_project_date_idx');
        });

        Schema::create('project_acp_liquidation_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_acp_liquidation_id');
            $table->string('original_name', 255);
            $table->string('attachment_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('project_acp_liquidation_id', 'acp_liq_attach_liq_fk')->references('id')->on('project_acp_liquidations')->cascadeOnDelete();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('module', 100);
            $table->string('auditable_type', 255);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_type_auditable_id_index');
            $table->index(['user_id', 'performed_at'], 'audit_logs_user_id_performed_at_index');
            $table->index(['module', 'performed_at'], 'audit_logs_module_performed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('project_acp_liquidation_attachments');
        Schema::dropIfExists('project_acp_liquidations');
        Schema::dropIfExists('project_acp_check_release_attachments');
        Schema::dropIfExists('project_acp_check_releases');
        Schema::dropIfExists('project_acp_payments');
        Schema::dropIfExists('project_labor_market_referrals');
        Schema::dropIfExists('project_beneficiary_sectors');
    }
};
