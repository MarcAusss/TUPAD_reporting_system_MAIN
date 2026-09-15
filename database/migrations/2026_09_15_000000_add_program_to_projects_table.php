<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Program is encoded by the TUPAD Coordinator alongside Partner in the
     * Funding Information section. Nullable since existing projects were
     * created before this field existed; new projects require it.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('program', 255)->nullable()->after('partner');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('program');
        });
    }
};
