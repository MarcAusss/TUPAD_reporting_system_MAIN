<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ADL Total is now Grants + Administrative Cost (previously Grants only,
     * matching an allocation's own total_amount = grant_amount + admin_cost_amount).
     * Recompute existing rows so stored totals reflect the corrected rule
     * rather than only new records going forward.
     */
    public function up(): void
    {
        DB::table('adls')->update([
            'total' => DB::raw('grants + admin_cost'),
        ]);
    }

    public function down(): void
    {
        DB::table('adls')->update([
            'total' => DB::raw('grants'),
        ]);
    }
};
