<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('adls', function (Blueprint $table) {
            $table->date('date_received')->nullable()->after('adl_number');
            $table->string('batch', 100)->nullable()->after('date_received');
            $table->string('tranche', 100)->nullable()->after('batch');
            $table->string('sponsor_reference', 255)->nullable()->after('tranche');
            $table->date('nfa_date')->nullable()->after('sponsor_reference');
            $table->string('nfa_number', 150)->nullable()->after('nfa_date');
            $table->date('nta_date')->nullable()->after('nfa_number');
            $table->string('nta_number', 150)->nullable()->after('nta_date');
        });

        Schema::table('adl_allocations', function (Blueprint $table) {
            $table->string('local_chief_executive_partylist', 255)->nullable()->after('partner');
            $table->string('province', 150)->nullable()->after('location');
            $table->string('district', 100)->nullable()->after('province');
            $table->string('municipality', 150)->nullable()->after('district');
            $table->decimal('grant_amount', 15, 2)->nullable()->after('amount');
            $table->decimal('admin_cost_amount', 15, 2)->default(0)->after('grant_amount');
            $table->decimal('total_amount', 15, 2)->nullable()->after('admin_cost_amount');
            $table->index(['province', 'municipality'], 'adl_allocations_province_municipality_index');
        });

        DB::table('adl_allocations')->whereNull('grant_amount')->update([
            'grant_amount' => DB::raw('amount'),
            'total_amount' => DB::raw('amount'),
        ]);

        Schema::table('adl_realignments', function (Blueprint $table) {
            $table->date('maf_date')->nullable()->after('realignment_date');
            $table->string('maf_number', 150)->nullable()->after('maf_date');
        });

        DB::table('adl_realignments')->whereNull('maf_date')->update([
            'maf_date' => DB::raw('realignment_date'),
            'maf_number' => DB::raw('reference_number'),
        ]);

        Schema::table('project_beneficiaries', function (Blueprint $table) {
            $table->boolean('is_pwd')->default(false)->after('contact_number');
            $table->boolean('is_rebel_returnee')->default(false)->after('is_pwd');
            $table->decimal('grant_amount', 15, 2)->nullable()->after('is_rebel_returnee');
        });

        Schema::create('project_monitoring_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_monitoring_details');

        Schema::table('project_beneficiaries', function (Blueprint $table) {
            $table->dropColumn(['is_pwd', 'is_rebel_returnee', 'grant_amount']);
        });

        Schema::table('adl_realignments', function (Blueprint $table) {
            $table->dropColumn(['maf_date', 'maf_number']);
        });

        Schema::table('adl_allocations', function (Blueprint $table) {
            $table->dropIndex('adl_allocations_province_municipality_index');
            $table->dropColumn([
                'local_chief_executive_partylist',
                'province',
                'district',
                'municipality',
                'grant_amount',
                'admin_cost_amount',
                'total_amount',
            ]);
        });

        Schema::table('adls', function (Blueprint $table) {
            $table->dropColumn([
                'date_received',
                'batch',
                'tranche',
                'sponsor_reference',
                'nfa_date',
                'nfa_number',
                'nta_date',
                'nta_number',
            ]);
        });
    }
};
