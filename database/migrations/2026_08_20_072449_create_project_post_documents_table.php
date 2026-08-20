<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_post_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->date('date_received');

            $table->string('document_type', 255);

            $table->string('attachment_path')
                ->nullable();

            $table->text('remarks')
                ->nullable();

            $table->date('date_forwarded_to_imsd')
                ->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'project_id',
                'date_received',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_post_documents');
    }
};