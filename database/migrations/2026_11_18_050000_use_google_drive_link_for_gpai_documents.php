<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The On-call Payment Slip document is a Google Drive link instead of an uploaded file. */
    public function up(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->string('ops_document_url', 2048)->nullable()->after('ops_notes');
        });

        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->dropColumn(['ops_document_path', 'ops_document_name']);
        });
    }

    public function down(): void
    {
        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->string('ops_document_path')->nullable()->after('ops_notes');
            $table->string('ops_document_name')->nullable()->after('ops_document_path');
        });

        Schema::table('gpai_enrollments', function (Blueprint $table): void {
            $table->dropColumn('ops_document_url');
        });
    }
};
