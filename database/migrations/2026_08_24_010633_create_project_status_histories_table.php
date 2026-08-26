<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->string('from_status', 50)
                ->nullable();

            $table->string('to_status', 50);

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('remarks')
                ->nullable();

            $table->timestamp('changed_at');

            $table->timestamps();

            $table->index([
                'project_id',
                'changed_at',
            ]);

            $table->index('to_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_status_histories');
    }
};