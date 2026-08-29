<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_acp_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payee', 255);
            $table->string('payment_reference', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('payment_date');
        });

        Schema::create('project_acp_check_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->string('check_number', 150)->unique();
            $table->date('check_date');
            $table->decimal('amount', 15, 2);
            $table->date('released_date');
            $table->string('released_to', 255);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('released_date');
        });

        Schema::create('project_acp_check_release_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_acp_check_release_id');
            $table->foreign(
                'project_acp_check_release_id',
                'acp_release_attach_release_fk'
            )
                ->references('id')
                ->on('project_acp_check_releases')
                ->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('attachment_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_acp_check_release_attachments');
        Schema::dropIfExists('project_acp_check_releases');
        Schema::dropIfExists('project_acp_payments');
    }
};
