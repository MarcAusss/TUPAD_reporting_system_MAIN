<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Notice to Proceed's Date Issued / Date Released now also record a time,
     * not just a calendar date. Raw SQL is used instead of Blueprint::change()
     * since this project does not have doctrine/dbal installed.
     */
    public function up(): void
    {
        // SQLite has no MODIFY/ALTER COLUMN TYPE syntax, and its dynamic
        // typing means the declared column type is advisory only — the
        // application's date casts already handle DATETIME values fine
        // against a column declared DATE, so this is a safe no-op there.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            'ALTER TABLE project_notice_to_proceeds
                MODIFY date_issued DATETIME NOT NULL,
                MODIFY date_released DATETIME NOT NULL'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            'ALTER TABLE project_notice_to_proceeds
                MODIFY date_issued DATE NOT NULL,
                MODIFY date_released DATE NOT NULL'
        );
    }
};
