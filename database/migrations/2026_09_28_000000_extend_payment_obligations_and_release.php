<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Obligation tranches now record their own wages, insurance, and PPE
     * breakdown; `amount` remains the tranche total (wages + insurance + PPE).
     * Existing rows were wages-only, so they are backfilled accordingly.
     *
     * Projects also record when the Focal/Admin completed the obligation
     * tranches, which unlocks the TUPAD Coordinator's Release of Assistance.
     */
    public function up(): void
    {
        Schema::table('project_obligations', function (Blueprint $table): void {
            // Tranches are no longer capped at five.
            $table->unsignedSmallInteger('tranche_number')->default(1)->change();

            $table->decimal('wages_amount', 15, 2)->default(0)->after('beneficiaries_female');
            $table->decimal('insurance_amount', 15, 2)->default(0)->after('wages_amount');
            $table->decimal('ppe_amount', 15, 2)->default(0)->after('insurance_amount');
        });

        DB::table('project_obligations')->update([
            'wages_amount' => DB::raw('amount'),
        ]);

        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('obligations_completed_at')->nullable()->after('status');
            $table->foreignId('obligations_completed_by')
                ->nullable()
                ->after('obligations_completed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('obligations_completed_by');
            $table->dropColumn('obligations_completed_at');
        });

        Schema::table('project_obligations', function (Blueprint $table): void {
            $table->dropColumn(['wages_amount', 'insurance_amount', 'ppe_amount']);
            $table->unsignedTinyInteger('tranche_number')->default(1)->change();
        });
    }
};
