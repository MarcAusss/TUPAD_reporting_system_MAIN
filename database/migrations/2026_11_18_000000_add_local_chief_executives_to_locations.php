<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local Chief Executives: the Governor of each province and the Mayor of
     * each city / municipality. The Focal account maintains these names after
     * each local election (like District Representatives), and the ADL
     * allocation form uses them to fill the LCE field automatically.
     */
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table): void {
            $table->string('governor')->nullable()->after('name');
            $table->foreignId('governor_updated_by')
                ->nullable()
                ->after('governor')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('governor_updated_at')->nullable()->after('governor_updated_by');
        });

        Schema::table('municipalities', function (Blueprint $table): void {
            $table->string('mayor')->nullable()->after('name');
            $table->foreignId('mayor_updated_by')
                ->nullable()
                ->after('mayor')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('mayor_updated_at')->nullable()->after('mayor_updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mayor_updated_by');
            $table->dropColumn(['mayor', 'mayor_updated_at']);
        });

        Schema::table('provinces', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('governor_updated_by');
            $table->dropColumn(['governor', 'governor_updated_at']);
        });
    }
};
