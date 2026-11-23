<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Table 1 (Billing & Routing) remark: received via IMSD or email. */
    public function up(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->string('billing_received_via', 20)->nullable()->after('billing_status');
        });
    }

    public function down(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->dropColumn('billing_received_via');
        });
    }
};
