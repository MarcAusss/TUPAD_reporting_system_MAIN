<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('intervention_focus', 100)
                ->nullable()
                ->after('term');

            $table->index('intervention_focus');
        });

        Schema::create('project_beneficiary_sectors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('sector_group', 50);
            $table->string('sector_key', 100);
            $table->unsignedInteger('beneficiaries_total')->default(0);
            $table->unsignedInteger('beneficiaries_female')->default(0);

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['project_id', 'sector_key'],
                'project_beneficiary_sectors_project_key_unique'
            );

            $table->index(
                ['sector_group', 'sector_key'],
                'project_beneficiary_sectors_reporting_index'
            );
        });

        Schema::create('project_labor_market_referrals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->date('reporting_month');
            $table->string('program', 100);

            $table->unsignedInteger('interested_referred_total')->default(0);
            $table->unsignedInteger('interested_referred_female')->default(0);
            $table->unsignedInteger('provided_intervention_total')->default(0);
            $table->unsignedInteger('provided_intervention_female')->default(0);
            $table->decimal('amount_released', 15, 2)->default(0);
            $table->text('services_availed');

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['project_id', 'reporting_month', 'program'],
                'project_labor_referrals_month_program_unique'
            );

            $table->index(
                ['reporting_month', 'program'],
                'project_labor_referrals_reporting_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_labor_market_referrals');
        Schema::dropIfExists('project_beneficiary_sectors');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['intervention_focus']);
            $table->dropColumn('intervention_focus');
        });
    }
};
