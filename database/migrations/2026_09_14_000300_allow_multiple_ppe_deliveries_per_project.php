<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Multiple Delivery Receipts
        |--------------------------------------------------------------------------
        |
        | PPE may arrive in more than one batch (e.g. shirts in one receipt,
        | boots in another), so a project can now have several delivery
        | receipt rows instead of exactly one.
        |
        | The unique index on project_id also backs the project_id foreign key,
        | so a plain replacement index must exist before MySQL will let the
        | unique one be dropped.
        |
        */

        Schema::table('project_ppe_deliveries', function (Blueprint $table): void {
            $table->index('project_id', 'project_ppe_deliveries_project_id_index');
        });

        Schema::table('project_ppe_deliveries', function (Blueprint $table): void {
            $table->dropUnique('project_ppe_deliveries_project_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('project_ppe_deliveries', function (Blueprint $table): void {
            $table->dropIndex('project_ppe_deliveries_project_id_index');
            $table->unique('project_id', 'project_ppe_deliveries_project_id_unique');
        });
    }
};
