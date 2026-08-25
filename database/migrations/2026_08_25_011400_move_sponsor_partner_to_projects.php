<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('fund_sponsor', 255)
                ->nullable()
                ->after('nature_of_work');

            $table->string('partner', 255)
                ->nullable()
                ->after('fund_sponsor');

            $table->index('fund_sponsor');
            $table->index('partner');
        });

        /*
        |--------------------------------------------------------------------------
        | Portable historical backfill
        |--------------------------------------------------------------------------
        |
        | Do NOT use a joined UPDATE with DB::raw('adl_allocations.column').
        | Laravel translates that query differently for SQLite, and the outer
        | UPDATE cannot resolve the joined-table column alias.
        |
        | This row-based backfill works consistently on MySQL and SQLite.
        |
        */
        DB::table('projects')
            ->select([
                'projects.id',
                'projects.adl_allocation_id',
            ])
            ->whereNotNull('projects.adl_allocation_id')
            ->orderBy('projects.id')
            ->chunkById(500, function ($projects): void {
                $allocationIds = $projects
                    ->pluck('adl_allocation_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($allocationIds->isEmpty()) {
                    return;
                }

                $allocations = DB::table('adl_allocations')
                    ->whereIn('id', $allocationIds)
                    ->get([
                        'id',
                        'fund_sponsor',
                        'partner',
                    ])
                    ->keyBy('id');

                foreach ($projects as $project) {
                    $allocation = $allocations->get(
                        $project->adl_allocation_id
                    );

                    if (! $allocation) {
                        continue;
                    }

                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update([
                            'fund_sponsor' =>
                                $allocation->fund_sponsor,

                            'partner' =>
                                $allocation->partner,
                        ]);
                }
            }, 'projects.id', 'id');

        /*
        |--------------------------------------------------------------------------
        | Allocation columns become legacy / optional
        |--------------------------------------------------------------------------
        |
        | New allocations no longer ask the Focal user to encode Sponsor / Partner.
        | The columns remain for backward compatibility and rollback safety.
        |
        */
        Schema::table('adl_allocations', function (Blueprint $table) {
            $table->string('fund_sponsor', 255)
                ->nullable()
                ->change();

            $table->string('partner', 255)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Restore legacy allocation values before removing project fields
        |--------------------------------------------------------------------------
        */
        $allocations = DB::table('adl_allocations')
            ->select('id')
            ->get();

        foreach ($allocations as $allocation) {
            $project = DB::table('projects')
                ->where(
                    'adl_allocation_id',
                    $allocation->id
                )
                ->whereNotNull('fund_sponsor')
                ->whereNotNull('partner')
                ->orderBy('id')
                ->first();

            DB::table('adl_allocations')
                ->where('id', $allocation->id)
                ->update([
                    'fund_sponsor' =>
                        $project?->fund_sponsor
                        ?? 'Unassigned',

                    'partner' =>
                        $project?->partner
                        ?? 'Unassigned',
                ]);
        }

        Schema::table('adl_allocations', function (Blueprint $table) {
            $table->string('fund_sponsor', 255)
                ->nullable(false)
                ->change();

            $table->string('partner', 255)
                ->nullable(false)
                ->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['fund_sponsor']);
            $table->dropIndex(['partner']);

            $table->dropColumn([
                'fund_sponsor',
                'partner',
            ]);
        });
    }
};
