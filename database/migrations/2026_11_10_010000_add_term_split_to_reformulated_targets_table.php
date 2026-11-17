<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short-/long-term split of the Focal reformulated target. Nullable: rows
     * saved before the split keep only their combined total until re-saved.
     * When set, physical_target / financial_target_cents hold short + long.
     */
    public function up(): void
    {
        Schema::table('reformulated_targets', function (Blueprint $table): void {
            $table->unsignedInteger('short_term_physical_target')->nullable()->after('financial_target_cents');
            $table->unsignedBigInteger('short_term_financial_target_cents')->nullable()->after('short_term_physical_target');
            $table->unsignedInteger('long_term_physical_target')->nullable()->after('short_term_financial_target_cents');
            $table->unsignedBigInteger('long_term_financial_target_cents')->nullable()->after('long_term_physical_target');
        });
    }

    public function down(): void
    {
        Schema::table('reformulated_targets', function (Blueprint $table): void {
            $table->dropColumn([
                'short_term_physical_target',
                'short_term_financial_target_cents',
                'long_term_physical_target',
                'long_term_financial_target_cents',
            ]);
        });
    }
};
