<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Request GPAI Enrollment" presses: a TUPAD Coordinator (or Focal /
     * Admin) asks the GSIS Enrollment Officer to enroll a project before its
     * approval. At most 3 per project within any 3 hours.
     */
    public function up(): void
    {
        Schema::create('gpai_enrollment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpai_enrollment_requests');
    }
};
