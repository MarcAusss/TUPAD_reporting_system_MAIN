<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adl_realignments', function (Blueprint $table) {
            $table
                ->string('direction', 30)
                ->default('gip_to_tupad')
                ->after('adl_id');

            $table->index([
                'adl_id',
                'direction',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Backfill Existing Realignments
        |--------------------------------------------------------------------------
        |
        | The existing system already stores the financial effect in the sign
        | of amount:
        |
        |   positive = funds added to TUPAD
        |   negative = funds deducted from TUPAD
        |
        | Keep that signed amount convention because all existing ADL balance
        | and monitoring calculations already use SUM(amount).
        |
        */

        DB::table('adl_realignments')
            ->where('amount', '<', 0)
            ->update([
                'direction' => 'tupad_to_gip',
            ]);

        DB::table('adl_realignments')
            ->where('amount', '>=', 0)
            ->update([
                'direction' => 'gip_to_tupad',
            ]);
    }

    public function down(): void
    {
        Schema::table('adl_realignments', function (Blueprint $table) {
            $table->dropIndex([
                'adl_id',
                'direction',
            ]);

            $table->dropColumn('direction');
        });
    }
};
