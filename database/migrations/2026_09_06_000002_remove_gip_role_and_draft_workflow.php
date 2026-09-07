<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GIP draft encoding/review has been removed from the TUPAD system.
        // Retire former GIP accounts instead of deleting users so audit and
        // historical foreign-key references remain intact.
        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('role', 'gip')
                ->update([
                    'role' => 'retired',
                    'is_active' => false,
                    'assigned_province_id' => null,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('project_draft_ppe_items')) {
            Schema::drop('project_draft_ppe_items');
        }

        if (Schema::hasTable('project_drafts')) {
            Schema::drop('project_drafts');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'supervisor_tc_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('supervisor_tc_id');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'The GIP role/draft workflow removal is intentionally irreversible because the feature was retired before production go-live.'
        );
    }
};
