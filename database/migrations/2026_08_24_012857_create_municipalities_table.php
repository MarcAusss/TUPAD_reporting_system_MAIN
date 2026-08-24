<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('province_id')
                ->constrained('provinces')
                ->cascadeOnDelete();

            $table->string('code', 20)
                ->nullable();

            $table->string('name', 150);

            $table->string('district', 100)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Income Class
            |--------------------------------------------------------------------------
            |
            | This remains nullable until the official reference data is supplied.
            |
            */

            $table->string('income_class', 50)
                ->nullable();

            $table->boolean('is_city')
                ->default(false);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'province_id',
                'name',
            ]);

            $table->index([
                'province_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};