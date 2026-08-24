<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();

            $table->foreignId('municipality_id')
                ->constrained('municipalities')
                ->cascadeOnDelete();

            $table->string('code', 20)
                ->nullable();

            $table->string('name', 150);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Critical Constraint
            |--------------------------------------------------------------------------
            |
            | Barangay names only need to be unique inside a municipality.
            |
            */

            $table->unique([
                'municipality_id',
                'name',
            ]);

            $table->index([
                'municipality_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};