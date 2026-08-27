<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_obligations', function (Blueprint $table) {
            $table->unsignedTinyInteger('tranche_number')
                ->default(1)
                ->after('project_id');

            $table->unique(
                ['project_id', 'tranche_number'],
                'project_obligations_project_tranche_unique'
            );
        });

        Schema::table('project_obligations', function (Blueprint $table) {
            $table->dropUnique(['project_id']);
        });

        Schema::create('project_disbursements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_obligation_id')
                ->constrained('project_obligations')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->date('date_disbursed');
            $table->string('ldap_check_number', 150);
            $table->text('remarks')->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('date_disbursed');
            $table->unique(
                ['project_obligation_id', 'ldap_check_number'],
                'project_disbursements_obligation_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_disbursements');

        Schema::table('project_obligations', function (Blueprint $table) {
            $table->unique('project_id');
        });

        Schema::table('project_obligations', function (Blueprint $table) {
            $table->dropUnique(
                'project_obligations_project_tranche_unique'
            );

            $table->dropColumn('tranche_number');
        });
    }
};
