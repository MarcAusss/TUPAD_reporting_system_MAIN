<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evaluation attachments now come from two steps on the same evaluation
     * record: the TSSD evaluation (For Compliance findings) and the TC's
     * compliance submission. Existing rows were all compliance uploads.
     */
    public function up(): void
    {
        Schema::table('project_evaluation_attachments', function (Blueprint $table): void {
            $table->string('kind', 20)->default('compliance')->after('project_evaluation_id');
            $table->index(['project_evaluation_id', 'kind'], 'project_evaluation_attachments_kind_index');
        });
    }

    public function down(): void
    {
        Schema::table('project_evaluation_attachments', function (Blueprint $table): void {
            $table->dropIndex('project_evaluation_attachments_kind_index');
            $table->dropColumn('kind');
        });
    }
};
