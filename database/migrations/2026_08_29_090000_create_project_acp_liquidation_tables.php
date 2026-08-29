<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_acp_liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->date('liquidation_date');
            $table->decimal('amount', 15, 2);
            $table->string('liquidation_reference', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'liquidation_date'], 'acp_liq_project_date_idx');
        });

        Schema::create('project_acp_liquidation_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_acp_liquidation_id');
            $table->foreign(
                'project_acp_liquidation_id',
                'acp_liq_attach_liq_fk'
            )
                ->references('id')
                ->on('project_acp_liquidations')
                ->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('attachment_path', 500);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_acp_liquidation_attachments');
        Schema::dropIfExists('project_acp_liquidations');
    }
};
