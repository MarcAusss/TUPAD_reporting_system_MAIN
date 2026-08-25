<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_series', 100)
                ->nullable()
                ->after('partner');

            $table->text('project_series_remarks')
                ->nullable()
                ->after('project_series');

            $table->date('tevs_date_verified')
                ->nullable()
                ->after('project_series_remarks');

            $table->text('tevs_remarks')
                ->nullable()
                ->after('tevs_date_verified');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'project_series',
                'project_series_remarks',
                'tevs_date_verified',
                'tevs_remarks',
            ]);
        });
    }
};
