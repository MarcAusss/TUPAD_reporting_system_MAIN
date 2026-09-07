<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\TemporaryPasswordGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateInitialAdministrator extends Command
{
    protected $signature = 'tupad:initial-admin';

    protected $description = 'Create the first production Administrator from configured deployment values using a generated temporary password.';

    public function handle(TemporaryPasswordGenerator $passwordGenerator): int
    {
        if (! Schema::hasTable('users')) {
            $this->error('The users table does not exist. Run php artisan migrate --force first.');

            return self::FAILURE;
        }

        $active = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->where('is_active', true)
            ->first();

        if ($active) {
            $this->info("An active Administrator already exists: {$active->username}. Nothing was changed.");

            return self::SUCCESS;
        }

        if (User::query()->where('role', UserRole::ADMIN->value)->exists()) {
            $this->error('Administrator record(s) already exist but none are active. Resolve that account state explicitly instead of creating another bootstrap Administrator.');

            return self::FAILURE;
        }

        $name = trim((string) config('tupad_operations.initial_admin.name'));
        $username = trim((string) config('tupad_operations.initial_admin.username'));
        $email = trim((string) config('tupad_operations.initial_admin.email'));
        $position = trim((string) config('tupad_operations.initial_admin.position', 'System Administrator'));

        $missing = collect([
            'TUPAD_INITIAL_ADMIN_NAME' => $name,
            'TUPAD_INITIAL_ADMIN_USERNAME' => $username,
            'TUPAD_INITIAL_ADMIN_EMAIL' => $email,
        ])->filter(static fn (string $value): bool => $value === '')->keys();

        if ($missing->isNotEmpty()) {
            $this->error('Initial Administrator configuration is incomplete: '.$missing->implode(', '));
            $this->line('Set these values in the production .env, clear config cache, and run the command again.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('TUPAD_INITIAL_ADMIN_EMAIL must contain a valid email address.');

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $this->error('TUPAD_INITIAL_ADMIN_USERNAME must be 3-50 characters using letters, numbers, dot, underscore, or hyphen.');

            return self::FAILURE;
        }

        if (User::query()->where('username', $username)->orWhere('email', $email)->exists()) {
            $this->error('The configured initial Administrator username or email is already used by another account.');

            return self::FAILURE;
        }

        $temporaryPassword = $passwordGenerator->generate();

        $user = User::query()->create([
            'name' => Str::squish($name),
            'username' => $username,
            'email' => Str::lower($email),
            'position' => $position !== '' ? Str::squish($position) : 'System Administrator',
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'assigned_province_id' => null,
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $this->newLine();
        $this->info('Initial production Administrator created successfully.');
        $this->table(['Field', 'Value'], [
            ['Name', $user->name],
            ['Username', $user->username],
            ['Role', $user->roleLabel()],
            ['Temporary password', $temporaryPassword],
        ]);
        $this->warn('Record the temporary password securely now. It is not stored in plaintext and will not be displayed again.');
        $this->line('The account must change this password on first sign-in.');

        return self::SUCCESS;
    }
}
