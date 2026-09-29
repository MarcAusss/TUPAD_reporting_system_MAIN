<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supporting files uploaded with a compliance submission (the documents
     * TSSD required in its evaluation findings).
     */
    public function up(): void
    {
        Schema::create('project_evaluation_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_evaluation_id')->constrained('project_evaluations')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('attachment_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_evaluation_attachments');
    }
};
