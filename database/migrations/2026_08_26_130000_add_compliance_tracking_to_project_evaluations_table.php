<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $addComplianceDate =
            !Schema::hasColumn(
                'project_evaluations',
                'compliance_date'
            );

        $addCompliedBy =
            !Schema::hasColumn(
                'project_evaluations',
                'complied_by'
            );

        $addCompliedAt =
            !Schema::hasColumn(
                'project_evaluations',
                'complied_at'
            );

        if (
            !$addComplianceDate
            && !$addCompliedBy
            && !$addCompliedAt
        ) {
            return;
        }

        Schema::table(
            'project_evaluations',
            function (Blueprint $table) use ($addComplianceDate, $addCompliedBy, $addCompliedAt) {
                if ($addComplianceDate) {
                    $table
                        ->date(
                            'compliance_date'
                        )
                        ->nullable();
                }

                if ($addCompliedBy) {
                    $table
                        ->foreignId(
                            'complied_by'
                        )
                        ->nullable()
                        ->constrained(
                            'users'
                        )
                        ->nullOnDelete();
                }

                if ($addCompliedAt) {
                    $table
                        ->timestamp(
                            'complied_at'
                        )
                        ->nullable();
                }
            }
        );
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'project_evaluations',
                'complied_by'
            )
        ) {
            Schema::table(
                'project_evaluations',
                function (Blueprint $table) {
                    $table
                        ->dropConstrainedForeignId(
                            'complied_by'
                        );
                }
            );
        }

        $dropComplianceDate =
            Schema::hasColumn(
                'project_evaluations',
                'compliance_date'
            );

        $dropCompliedAt =
            Schema::hasColumn(
                'project_evaluations',
                'complied_at'
            );

        if (
            !$dropComplianceDate
            && !$dropCompliedAt
        ) {
            return;
        }

        Schema::table(
            'project_evaluations',
            function (Blueprint $table) use ($dropComplianceDate, $dropCompliedAt) {
                $columns = [];

                if ($dropComplianceDate) {
                    $columns[] =
                        'compliance_date';
                }

                if ($dropCompliedAt) {
                    $columns[] =
                        'complied_at';
                }

                $table->dropColumn(
                    $columns
                );
            }
        );
    }
};
