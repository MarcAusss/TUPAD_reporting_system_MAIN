<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->unique()
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->date('approval_date');

            $table->string('project_code', 100)
                ->unique();

            $table->text('remarks')
                ->nullable();

            $table->foreignId('approved_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('approved_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_approvals');
    }
};