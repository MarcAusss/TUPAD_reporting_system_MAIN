<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Long-term Direct Administration projects are paid in two or more
     * tranches. Each tranche starts with the TUPAD Coordinator's
     * post-documentary submission, which now states the tranche's
     * beneficiaries and the dates it covers. The Focal's obligation for that
     * tranche is linked back to the submission.
     */
    public function up(): void
    {
        Schema::table('project_post_documents', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tranche_number')->nullable()->after('project_id');
            $table->unsignedInteger('beneficiaries_total')->nullable()->after('tranche_number');
            $table->unsignedInteger('beneficiaries_female')->nullable()->after('beneficiaries_total');
            $table->date('coverage_start_date')->nullable()->after('beneficiaries_female');
            $table->date('coverage_end_date')->nullable()->after('coverage_start_date');
            $table->foreignId('coverage_updated_by')->nullable()->after('recorded_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('coverage_updated_at')->nullable()->after('coverage_updated_by');

            // Several legacy submissions may have no tranche (NULLs are not
            // compared), but a tranche number is used once per project.
            $table->unique(['project_id', 'tranche_number'], 'project_post_documents_project_tranche_unique');
        });

        Schema::table('project_obligations', function (Blueprint $table): void {
            $table->foreignId('project_post_document_id')->nullable()->after('project_id')
                ->constrained('project_post_documents')->nullOnDelete();

            $table->unique('project_post_document_id', 'project_obligations_post_document_unique');
        });
    }

    public function down(): void
    {
        Schema::table('project_obligations', function (Blueprint $table): void {
            $table->dropUnique('project_obligations_post_document_unique');
            $table->dropConstrainedForeignId('project_post_document_id');
        });

        Schema::table('project_post_documents', function (Blueprint $table): void {
            $table->dropUnique('project_post_documents_project_tranche_unique');
            $table->dropConstrainedForeignId('coverage_updated_by');
            $table->dropColumn([
                'tranche_number',
                'beneficiaries_total',
                'beneficiaries_female',
                'coverage_start_date',
                'coverage_end_date',
                'coverage_updated_at',
            ]);
        });
    }
};
