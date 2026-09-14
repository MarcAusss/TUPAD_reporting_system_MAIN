<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_evaluations', function (Blueprint $table): void {
            /*
            |--------------------------------------------------------------------------
            | Compliance Remarks
            |--------------------------------------------------------------------------
            |
            | What the TUPAD Coordinator actually submitted to comply with the TSSD
            | finding (e.g. which required documents were provided). Distinct from
            | `remarks`, which is the TSSD evaluator's own remark recorded at the time
            | of the "for_compliance" finding.
            |
            */

            $table->text('compliance_remarks')
                ->nullable()
                ->after('compliance_date');
        });
    }

    public function down(): void
    {
        Schema::table('project_evaluations', function (Blueprint $table): void {
            $table->dropColumn('compliance_remarks');
        });
    }
};
