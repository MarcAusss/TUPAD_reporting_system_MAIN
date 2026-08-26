<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_location_barangay')) {
            return;
        }

        $needsTotal =
            ! Schema::hasColumn(
                'project_location_barangay',
                'beneficiaries_total'
            );

        $needsFemale =
            ! Schema::hasColumn(
                'project_location_barangay',
                'beneficiaries_female'
            );

        if ($needsTotal || $needsFemale) {
            Schema::table(
                'project_location_barangay',
                function (Blueprint $table) use (
                    $needsTotal,
                    $needsFemale
                ) {
                    if ($needsTotal) {
                        $table
                            ->unsignedInteger(
                                'beneficiaries_total'
                            )
                            ->nullable();
                    }

                    if ($needsFemale) {
                        $table
                            ->unsignedInteger(
                                'beneficiaries_female'
                            )
                            ->nullable();
                    }
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Safe single-barangay backfill
        |--------------------------------------------------------------------------
        |
        | If this compatibility migration had to add the fields because the
        | Phase 14 migration was missed, only a one-barangay project can be
        | reconstructed exactly from the project aggregate. Multi-barangay
        | legacy projects intentionally remain NULL rather than inventing a
        | beneficiary split.
        |
        */

        DB::table('projects')
            ->select([
                'id',
                'beneficiaries_total',
                'beneficiaries_female',
            ])
            ->orderBy('id')
            ->chunkById(
                200,
                function ($projects) {
                    foreach ($projects as $project) {
                        $rows =
                            DB::table(
                                'project_location_barangay'
                            )
                            ->join(
                                'project_locations',
                                'project_locations.id',
                                '=',
                                'project_location_barangay.project_location_id'
                            )
                            ->where(
                                'project_locations.project_id',
                                $project->id
                            )
                            ->select(
                                'project_location_barangay.id'
                            )
                            ->get();

                        if ($rows->count() !== 1) {
                            continue;
                        }

                        DB::table(
                            'project_location_barangay'
                        )
                            ->where(
                                'id',
                                $rows->first()->id
                            )
                            ->whereNull(
                                'beneficiaries_total'
                            )
                            ->update([
                                'beneficiaries_total' =>
                                    (int) $project
                                        ->beneficiaries_total,

                                'beneficiaries_female' =>
                                    (int) $project
                                        ->beneficiaries_female,
                            ]);
                    }
                }
            );
    }

    public function down(): void
    {
        /*
         * Compatibility migration intentionally does not drop the columns.
         * They belong to the Phase 14 data model and may have been created by
         * the original Phase 14 migration.
         */
    }
};
