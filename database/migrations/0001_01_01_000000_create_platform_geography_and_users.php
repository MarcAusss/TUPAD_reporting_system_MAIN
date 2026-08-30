<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->nullable();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('code', 'provinces_code_unique');
            $table->unique('name', 'provinces_name_unique');
            $table->index('name', 'provinces_name_index');
            $table->index('is_active', 'provinces_is_active_index');
        });

        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('name', 150);
            $table->string('district', 100)->nullable();
            $table->string('income_class', 50)->nullable();
            $table->boolean('is_city')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['province_id', 'name'], 'municipalities_province_id_name_unique');
            $table->index(['province_id', 'is_active'], 'municipalities_province_id_is_active_index');
        });

        Schema::create('barangays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained('municipalities')->cascadeOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['municipality_id', 'name'], 'barangays_municipality_id_name_unique');
            $table->index(['municipality_id', 'is_active'], 'barangays_municipality_id_is_active_index');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('username', 50);
            $table->string('position', 255)->nullable();
            $table->string('role', 20);
            $table->boolean('is_active')->default(true);
            $table->foreignId('supervisor_tc_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_province_id')->nullable()->constrained('provinces')->restrictOnDelete();

            $table->unique('email', 'users_email_unique');
            $table->unique('username', 'users_username_unique');
            $table->index('role', 'users_role_index');
            $table->index('is_active', 'users_is_active_index');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 255);
            $table->string('token', 255);
            $table->timestamp('created_at')->nullable();

            $table->primary('email', 'password_reset_tokens_email_primary');
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id', 255);
            $table->foreignId('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');

            $table->primary('id', 'sessions_id_primary');
            $table->index('user_id', 'sessions_user_id_index');
            $table->index('last_activity', 'sessions_last_activity_index');
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key', 255);
            $table->mediumText('value');
            $table->bigInteger('expiration');

            $table->primary('key', 'cache_key_primary');
            $table->index('expiration', 'cache_expiration_index');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key', 255);
            $table->string('owner', 255);
            $table->bigInteger('expiration');

            $table->primary('key', 'cache_locks_key_primary');
            $table->index('expiration', 'cache_locks_expiration_index');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue', 255);
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');

            $table->index('queue', 'jobs_queue_index');
        });

        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id', 255);
            $table->string('name', 255);
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();

            $table->primary('id', 'job_batches_id_primary');
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 255);
            $table->string('connection', 255);
            $table->string('queue', 255);
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->unique('uuid', 'failed_jobs_uuid_unique');
            $table->index(['connection', 'queue', 'failed_at'], 'failed_jobs_connection_queue_failed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('barangays');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('provinces');
    }
};
