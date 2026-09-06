<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')
                ->default(false)
                ->after('password');

            $table->timestamp('password_changed_at')
                ->nullable()
                ->after('must_change_password');
        });

        // Existing installations may contain accounts created with the old
        // development credential. Do not lock those users out during migration;
        // instead, require replacement immediately after their next successful
        // sign-in. Production release verification will continue to block while
        // any active account still retains that credential.
        DB::table('users')
            ->select(['id', 'password'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                try {
                    $usesLegacyDevelopmentPassword = Hash::check(
                        'password',
                        (string) $user->password,
                    );
                } catch (\Throwable) {
                    $usesLegacyDevelopmentPassword = false;
                }

                if ($usesLegacyDevelopmentPassword) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'must_change_password' => true,
                            'password_changed_at' => null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'must_change_password',
                'password_changed_at',
            ]);
        });
    }
};
