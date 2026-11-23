<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            // Table 1 (billing and routing) status, e.g. "rd", "signed_completed".
            $table->string('billing_status', 40)->nullable()->after('distributed_on');
            // Table 2 (on-call payment slip) status, e.g. "complete", "for_or_only".
            $table->string('ops_status', 40)->nullable()->after('billing_status');
            $table->text('ops_remarks')->nullable()->after('ops_status');
            $table->text('ops_notes')->nullable()->after('ops_remarks');
            $table->string('ops_document_path')->nullable()->after('ops_notes');
            $table->string('ops_document_name')->nullable()->after('ops_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_status',
                'ops_status',
                'ops_remarks',
                'ops_notes',
                'ops_document_path',
                'ops_document_name',
            ]);
        });
    }
};
