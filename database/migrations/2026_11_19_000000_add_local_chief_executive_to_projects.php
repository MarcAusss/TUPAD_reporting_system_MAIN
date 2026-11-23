<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local Chief Executive of the project's location (e.g. "Mayor X /
     * Governor Y"), filled automatically from Mayors & Governors on Create
     * Project and editable by the TUPAD Coordinator.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('local_chief_executive')->nullable()->after('income_class');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('local_chief_executive');
        });
    }
};
