<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A TUPAD Coordinator given the Focal access level. The account's role is
     * Focal (so every Focal permission applies unchanged), while this flag makes
     * it display as "TUPAD Coordinator (Focal Access)" everywhere.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('has_focal_access')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('has_focal_access');
        });
    }
};
