<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_orientations', function (Blueprint $table): void {
            $table->unsignedInteger('beneficiaries_oriented')->nullable()->after('orientation_date');
            $table->string('venue', 255)->nullable()->after('beneficiaries_oriented');
            $table->string('oriented_by', 255)->nullable()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('project_orientations', function (Blueprint $table): void {
            $table->dropColumn(['beneficiaries_oriented', 'venue', 'oriented_by']);
        });
    }
};
