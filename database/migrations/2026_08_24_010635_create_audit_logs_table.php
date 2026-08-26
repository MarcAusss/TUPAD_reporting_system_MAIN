<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 50);

            $table->string('module', 100);

            $table->string('auditable_type', 255);

            $table->unsignedBigInteger('auditable_id');

            $table->json('old_values')
                ->nullable();

            $table->json('new_values')
                ->nullable();

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->timestamp('performed_at');

            $table->timestamps();

            $table->index([
                'auditable_type',
                'auditable_id',
            ]);

            $table->index([
                'user_id',
                'performed_at',
            ]);

            $table->index([
                'module',
                'performed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};