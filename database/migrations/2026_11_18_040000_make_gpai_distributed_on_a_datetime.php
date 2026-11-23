<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Distributed to PCs" is now recorded automatically, with date and time,
     * when the system notifies the TUPAD Coordinators that the OR and Policy
     * were received.
     */
    public function up(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->dateTime('distributed_on')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->date('distributed_on')->nullable()->change();
        });
    }
};
