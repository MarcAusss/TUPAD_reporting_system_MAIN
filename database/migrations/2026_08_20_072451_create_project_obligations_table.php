<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_obligations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->unique()
                ->constrained('projects')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Snapshot Information
            |--------------------------------------------------------------------------
            |
            | These values preserve what was used at payment time.
            |
            */

            $table->string('adl_number', 100);
            $table->string('fund_sponsor', 255);
            $table->string('partner', 255);

            $table->string('project_location', 500);

            $table->string('term', 50);

            $table->unsignedInteger('beneficiaries_total');
            $table->unsignedInteger('beneficiaries_female');

            $table->decimal('amount', 15, 2);

            $table->date('obligation_date');

            $table->string('month', 30);

            $table->string('payee', 255);

            $table->text('remarks')
                ->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('obligation_date');
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_obligations');
    }
};