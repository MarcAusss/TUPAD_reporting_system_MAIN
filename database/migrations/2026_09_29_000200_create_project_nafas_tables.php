<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NAFA (Notice of Availability of Fund) for Through ACP projects,
     * recorded during implementation preparation after the check release.
     */
    public function up(): void
    {
        Schema::create('project_nafas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('nafa_date');
            $table->date('release_date');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique('project_id', 'project_nafas_project_id_unique');
        });

        Schema::create('project_nafa_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_nafa_id')->constrained('project_nafas')->cascadeOnDelete();
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
        Schema::dropIfExists('project_nafa_attachments');
        Schema::dropIfExists('project_nafas');
    }
};
