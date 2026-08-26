<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adls', function (Blueprint $table) {
            $table->id();

            $table->string('adl_number', 100)->unique();

            $table->decimal('grants', 15, 2);
            $table->decimal('admin_cost', 15, 2)->default(0);
            $table->decimal('total', 15, 2);

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('adl_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adls');
    }
};