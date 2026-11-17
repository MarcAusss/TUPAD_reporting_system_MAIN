<?php

use App\Support\MunicipalityIncomeClasses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->foreignId('income_class_updated_by')->nullable()->after('income_class')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('income_class_updated_at')->nullable()->after('income_class_updated_by');
        });

        // Existing databases already have municipalities; fresh installs get
        // the same baseline from the seeder after the geography is loaded.
        MunicipalityIncomeClasses::applyInitial();
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('income_class_updated_by');
            $table->dropColumn('income_class_updated_at');
        });
    }
};
