<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adls', function (Blueprint $table): void {
            $table->id();
            $table->string('adl_number', 100);
            $table->decimal('grants', 15, 2);
            $table->decimal('admin_cost', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->date('date_received')->nullable();
            $table->string('batch', 100)->nullable();
            $table->string('tranche', 100)->nullable();
            $table->string('sponsor_reference', 255)->nullable();
            $table->date('nfa_date')->nullable();
            $table->string('nfa_number', 150)->nullable();
            $table->date('nta_date')->nullable();
            $table->string('nta_number', 150)->nullable();

            $table->unique('adl_number', 'adls_adl_number_unique');
            $table->index('adl_number', 'adls_adl_number_index');
        });

        Schema::create('adl_realignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adl_id')->constrained('adls')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reference_number', 150)->nullable();
            $table->date('realignment_date');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->date('maf_date')->nullable();
            $table->string('maf_number', 150)->nullable();
            $table->string('direction', 30)->default('gip_to_tupad');

            $table->index(['adl_id', 'realignment_date'], 'adl_realignments_adl_id_realignment_date_index');
            $table->index(['adl_id', 'direction'], 'adl_realignments_adl_id_direction_index');
        });

        Schema::create('adl_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adl_id')->constrained('adls')->cascadeOnDelete();
            $table->string('fund_sponsor', 255)->nullable();
            $table->string('partner', 255)->nullable();
            $table->string('location', 255);
            $table->decimal('amount', 15, 2);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('local_chief_executive_partylist', 255)->nullable();
            $table->string('province', 150)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('municipality', 150)->nullable();
            $table->decimal('grant_amount', 15, 2)->nullable();
            $table->decimal('admin_cost_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->nullable();

            $table->index('adl_id', 'adl_allocations_adl_id_index');
            $table->index('fund_sponsor', 'adl_allocations_fund_sponsor_index');
            $table->index('partner', 'adl_allocations_partner_index');
            $table->index(['province', 'municipality'], 'adl_allocations_province_municipality_index');
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adl_allocation_id')->constrained('adl_allocations')->restrictOnDelete();
            $table->date('date_received');
            $table->string('project_title', 255);
            $table->text('nature_of_work');
            $table->string('province', 150);
            $table->string('district', 100);
            $table->string('municipality', 150);
            $table->string('barangay', 150);
            $table->string('income_class', 50)->nullable();
            $table->string('implementation_mode', 50);
            $table->unsignedSmallInteger('number_of_days');
            $table->string('term', 30);
            $table->unsignedInteger('beneficiaries_total');
            $table->unsignedInteger('beneficiaries_female')->default(0);
            $table->decimal('wage_rate', 12, 2);
            $table->decimal('wages_total', 15, 2);
            $table->decimal('ppe_total', 15, 2)->default(0);
            $table->decimal('insurance_rate', 12, 2)->default(50);
            $table->decimal('insurance_total', 15, 2)->default(0);
            $table->decimal('total_project_cost', 15, 2);
            $table->string('status', 50)->default('ongoing_profiling');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->string('fund_sponsor', 255)->nullable();
            $table->string('partner', 255)->nullable();
            $table->string('project_series', 100)->nullable();
            $table->text('project_series_remarks')->nullable();
            $table->date('tevs_date_verified')->nullable();
            $table->text('tevs_remarks')->nullable();
            $table->unsignedInteger('insurance_beneficiaries')->nullable();
            $table->string('intervention_focus', 100)->nullable();

            $table->index('status', 'projects_status_index');
            $table->index(['province', 'municipality', 'barangay'], 'projects_province_municipality_barangay_index');
            $table->index('adl_allocation_id', 'projects_adl_allocation_id_index');
            $table->index(['province_id', 'municipality_id', 'barangay_id'], 'projects_location_reference_index');
            $table->index(['status', 'date_received'], 'projects_status_date_index');
            $table->index('fund_sponsor', 'projects_fund_sponsor_index');
            $table->index('partner', 'projects_partner_index');
            $table->index('intervention_focus', 'projects_intervention_focus_index');
        });

        Schema::create('project_ppe_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('ppe_type', 30);
            $table->string('product', 255);
            $table->unsignedInteger('beneficiary_count');
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('total_amount', 15, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['project_id', 'ppe_type'], 'project_ppe_items_project_id_ppe_type_index');
        });

        Schema::create('project_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encoded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_tc_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('adl_allocation_id')->constrained('adl_allocations')->restrictOnDelete();
            $table->date('date_received');
            $table->string('project_title', 255);
            $table->text('nature_of_work');
            $table->string('province', 150);
            $table->string('district', 100);
            $table->string('municipality', 150);
            $table->string('barangay', 150);
            $table->string('income_class', 50)->nullable();
            $table->string('implementation_mode', 50);
            $table->unsignedSmallInteger('number_of_days');
            $table->string('term', 30);
            $table->unsignedInteger('beneficiaries_total');
            $table->unsignedInteger('beneficiaries_female')->default(0);
            $table->decimal('wage_rate', 12, 2);
            $table->decimal('wages_total', 15, 2);
            $table->decimal('ppe_total', 15, 2)->default(0);
            $table->decimal('insurance_rate', 12, 2)->default(50);
            $table->decimal('insurance_total', 15, 2)->default(0);
            $table->decimal('total_project_cost', 15, 2);
            $table->string('status', 50)->default('draft');
            $table->text('remarks')->nullable();
            $table->text('tc_review_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained('barangays')->nullOnDelete();

            $table->index('status', 'project_drafts_status_index');
            $table->index(['assigned_tc_id', 'status'], 'project_drafts_assigned_tc_id_status_index');
            $table->index(['encoded_by', 'status'], 'project_drafts_encoded_by_status_index');
        });

        Schema::create('project_draft_ppe_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_draft_id')->constrained('project_drafts')->cascadeOnDelete();
            $table->string('ppe_type', 30);
            $table->string('product', 255);
            $table->unsignedInteger('beneficiary_count');
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('total_amount', 15, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['project_draft_id', 'ppe_type'], 'project_draft_ppe_items_project_draft_id_ppe_type_index');
        });

        Schema::create('project_beneficiaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 30)->nullable();
            $table->string('sex', 10);
            $table->date('birth_date')->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_rebel_returnee')->default(false);
            $table->decimal('grant_amount', 15, 2)->nullable();

            $table->index(['project_id', 'sex'], 'project_beneficiaries_project_id_sex_index');
            $table->index(['project_id', 'last_name', 'first_name'], 'project_beneficiaries_project_id_last_name_first_name_index');
            $table->index(['project_id', 'last_name', 'first_name'], 'project_beneficiaries_project_name_index');
        });

        Schema::create('project_monitoring_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('project_series', 255)->nullable();
            $table->string('proponent', 255)->nullable();
            $table->string('receipt_month', 30)->nullable();
            $table->dateTime('receipt_datetime')->nullable();
            $table->unsignedSmallInteger('process_cycle_days')->nullable();
            $table->date('compliance_date')->nullable();
            $table->string('compliance_reference', 150)->nullable();
            $table->string('agreement_type', 50)->nullable();
            $table->date('agreement_date')->nullable();
            $table->string('agreement_reference', 150)->nullable();
            $table->date('replacement_request_date')->nullable();
            $table->date('replacement_ntp_date')->nullable();
            $table->date('voucher_date')->nullable();
            $table->string('voucher_number', 150)->nullable();
            $table->date('nafa_date')->nullable();
            $table->string('nafa_number', 150)->nullable();
            $table->date('sprs_date')->nullable();
            $table->date('cqpr_date')->nullable();
            $table->date('transparency_seal_date')->nullable();
            $table->text('monitoring_remarks')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('project_id', 'project_monitoring_details_project_id_unique');
        });

        Schema::create('project_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $table->foreignId('municipality_id')->constrained('municipalities')->restrictOnDelete();
            $table->string('district', 100);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['project_id', 'municipality_id'], 'project_locations_project_id_municipality_id_unique');
            $table->index(['project_id', 'sort_order'], 'project_locations_project_id_sort_order_index');
        });

        Schema::create('project_location_barangay', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_location_id')->constrained('project_locations')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedInteger('beneficiaries_total')->nullable();
            $table->unsignedInteger('beneficiaries_female')->nullable();

            $table->unique(['project_location_id', 'barangay_id'], 'project_location_barangay_project_location_id_barangay_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_location_barangay');
        Schema::dropIfExists('project_locations');
        Schema::dropIfExists('project_monitoring_details');
        Schema::dropIfExists('project_beneficiaries');
        Schema::dropIfExists('project_draft_ppe_items');
        Schema::dropIfExists('project_drafts');
        Schema::dropIfExists('project_ppe_items');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('adl_allocations');
        Schema::dropIfExists('adl_realignments');
        Schema::dropIfExists('adls');
    }
};
