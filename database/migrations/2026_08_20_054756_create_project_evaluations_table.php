<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_evaluations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->text('findings')
                ->nullable();

            $table->text('required_documents')
                ->nullable();

            $table->text('remarks')
                ->nullable();

            $table->string('result', 30);

            $table->foreignId('evaluated_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('evaluated_at');

            $table->timestamps();

            $table->index([
                'project_id',
                'evaluated_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_evaluations');
    }
};