<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Project Approvals
        |--------------------------------------------------------------------------
        */

        Schema::table('project_approvals', function (Blueprint $table) {
            /*
            | One project may only have one approval record.
            |
            | If these indexes already exist in your original migration,
            | DO NOT duplicate them here.
            */

            if (
                !$this->indexExists(
                    'project_approvals',
                    'project_approvals_project_id_unique'
                )
            ) {
                $table->unique(
                    'project_id',
                    'project_approvals_project_id_unique'
                );
            }

            if (
                !$this->indexExists(
                    'project_approvals',
                    'project_approvals_project_code_unique'
                )
            ) {
                $table->unique(
                    'project_code',
                    'project_approvals_project_code_unique'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Project Beneficiaries
        |--------------------------------------------------------------------------
        */

        Schema::table('project_beneficiaries', function (Blueprint $table) {
            if (
                !$this->indexExists(
                    'project_beneficiaries',
                    'project_beneficiaries_project_id_sex_index'
                )
            ) {
                $table->index(
                    [
                        'project_id',
                        'sex',
                    ],
                    'project_beneficiaries_project_id_sex_index'
                );
            }

            if (
                !$this->indexExists(
                    'project_beneficiaries',
                    'project_beneficiaries_project_name_index'
                )
            ) {
                $table->index(
                    [
                        'project_id',
                        'last_name',
                        'first_name',
                    ],
                    'project_beneficiaries_project_name_index'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Geographic Master Data
        |--------------------------------------------------------------------------
        */

        Schema::table('municipalities', function (Blueprint $table) {
            if (
                !$this->indexExists(
                    'municipalities',
                    'municipalities_province_id_name_unique'
                )
            ) {
                $table->unique(
                    [
                        'province_id',
                        'name',
                    ],
                    'municipalities_province_id_name_unique'
                );
            }
        });

        Schema::table('barangays', function (Blueprint $table) {
            if (
                !$this->indexExists(
                    'barangays',
                    'barangays_municipality_id_name_unique'
                )
            ) {
                $table->unique(
                    [
                        'municipality_id',
                        'name',
                    ],
                    'barangays_municipality_id_name_unique'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Project Lookup Indexes
        |--------------------------------------------------------------------------
        */

        Schema::table('projects', function (Blueprint $table) {
            if (
                !$this->indexExists(
                    'projects',
                    'projects_location_reference_index'
                )
            ) {
                $table->index(
                    [
                        'province_id',
                        'municipality_id',
                        'barangay_id',
                    ],
                    'projects_location_reference_index'
                );
            }

            if (
                !$this->indexExists(
                    'projects',
                    'projects_status_date_index'
                )
            ) {
                $table->index(
                    [
                        'status',
                        'date_received',
                    ],
                    'projects_status_date_index'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $this->dropIndexIfExists(
                $table,
                'projects',
                'projects_location_reference_index'
            );

            $this->dropIndexIfExists(
                $table,
                'projects',
                'projects_status_date_index'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Do not automatically remove core uniqueness constraints here.
        |--------------------------------------------------------------------------
        |
        | Those constraints represent valid production invariants.
        |
        */
    }

    private function indexExists(
        string $table,
        string $indexName
    ): bool {
        return collect(
            Schema::getIndexes($table)
        )->contains(
                fn(array $index) =>
                ($index['name'] ?? null)
                === $indexName
            );
    }

    private function dropIndexIfExists(
        Blueprint $table,
        string $tableName,
        string $indexName
    ): void {
        if (
            $this->indexExists(
                $tableName,
                $indexName
            )
        ) {
            $table->dropIndex(
                $indexName
            );
        }
    }
};