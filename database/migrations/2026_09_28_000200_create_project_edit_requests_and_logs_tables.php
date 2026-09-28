<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Overview sections can be edited directly by the Focal/Admin. A TUPAD
     * Coordinator must first request permission; a Focal/Admin approval
     * unlocks that one section record for a single save. Every save is
     * logged with its field-level changes for the "edited" note.
     */
    public function up(): void
    {
        Schema::create('project_edit_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('section', 50);
            $table->unsignedBigInteger('record_id');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'section', 'record_id', 'status'], 'project_edit_requests_target_index');
            $table->index('status', 'project_edit_requests_status_index');
        });

        Schema::create('project_edit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('section', 50);
            $table->unsignedBigInteger('record_id');
            $table->json('changes');
            $table->foreignId('edited_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('edit_request_id')->nullable()->constrained('project_edit_requests')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'section', 'record_id'], 'project_edit_logs_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_edit_logs');
        Schema::dropIfExists('project_edit_requests');
    }
};
