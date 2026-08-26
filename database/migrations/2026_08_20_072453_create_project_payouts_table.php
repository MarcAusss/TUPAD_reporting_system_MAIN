<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->unique()
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->date('payout_date');

            $table->string('payout_mode', 100);

            $table->string('venue', 255);

            $table->text('remarks')
                ->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('payout_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_payouts');
    }
};