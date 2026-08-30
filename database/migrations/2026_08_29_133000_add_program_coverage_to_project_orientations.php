<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_orientations', function (Blueprint $table) {
            $table->boolean('alkansssya_conducted')
                ->default(false)
                ->after('orientation_date');
            $table->boolean('yakap_conducted')
                ->default(false)
                ->after('alkansssya_conducted');
        });
    }

    public function down(): void
    {
        Schema::table('project_orientations', function (Blueprint $table) {
            $table->dropColumn([
                'alkansssya_conducted',
                'yakap_conducted',
            ]);
        });
    }
};
