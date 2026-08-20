<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)
                ->unique()
                ->after('name');

            $table->string('position')
                ->nullable()
                ->after('email');

            $table->string('role', 20)
                ->after('position');

            $table->boolean('is_active')
                ->default(true)
                ->after('role');

            $table->foreignId('supervisor_tc_id')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supervisor_tc_id']);

            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);

            $table->dropColumn([
                'username',
                'position',
                'role',
                'is_active',
                'supervisor_tc_id',
            ]);
        });
    }
};