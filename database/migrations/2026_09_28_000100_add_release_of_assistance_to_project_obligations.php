<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Release of Assistance is recorded per obligation tranche by the
     * TUPAD Coordinator, after the Focal has fully disbursed that tranche.
     * (project_payouts remains for historical project-level records.)
     */
    public function up(): void
    {
        Schema::table('project_obligations', function (Blueprint $table): void {
            $table->string('release_mode', 100)->nullable()->after('remarks');
            $table->date('release_date')->nullable()->after('release_mode');
            $table->string('release_venue', 255)->nullable()->after('release_date');
            $table->text('release_remarks')->nullable()->after('release_venue');
            $table->foreignId('released_by')
                ->nullable()
                ->after('release_remarks')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('released_at')->nullable()->after('released_by');

            $table->index('release_date', 'project_obligations_release_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('project_obligations', function (Blueprint $table): void {
            $table->dropIndex('project_obligations_release_date_index');
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn(['release_mode', 'release_date', 'release_venue', 'release_remarks', 'released_at']);
        });
    }
};
