<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adl_realignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('adl_id')
                ->constrained('adls')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Amount
            |--------------------------------------------------------------------------
            |
            | Positive  = additional funds
            | Negative  = deduction from available funds
            |
            */

            $table->decimal('amount', 15, 2);

            $table->string('reference_number', 150)->nullable();

            $table->date('realignment_date');

            $table->text('reason')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'adl_id',
                'realignment_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adl_realignments');
    }
};