<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment of Wages → Insurance and PPE.
     *
     * The insurance and PPE actually payable once beneficiaries not included
     * in the obligation tranches are deducted. Saved separately from the
     * tranches; amounts are computed by the server, never typed in.
     */
    public function up(): void
    {
        Schema::create('project_payment_insurance_ppe', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->unsignedInteger('beneficiaries_paid');
            $table->unsignedInteger('beneficiaries_deducted');

            $table->unsignedInteger('insurance_beneficiaries');
            $table->decimal('insurance_deducted', 15, 2);
            $table->decimal('insurance_amount', 15, 2);

            $table->decimal('ppe_deducted', 15, 2);
            $table->decimal('ppe_amount', 15, 2);
            $table->json('ppe_breakdown');

            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique('project_id', 'project_payment_insurance_ppe_project_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_payment_insurance_ppe');
    }
};
