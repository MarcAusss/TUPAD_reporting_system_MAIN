<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'position',
        'role',
        'is_active',
        'supervisor_tc_id',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    public function supervisorTc(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'supervisor_tc_id'
        );
    }

    public function assignedGips(): HasMany
    {
        return $this->hasMany(
            User::class,
            'supervisor_tc_id'
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isTc(): bool
    {
        return $this->role === UserRole::TC;
    }

    public function isGip(): bool
    {
        return $this->role === UserRole::GIP;
    }

    public function isFocal(): bool
    {
        return $this->role === UserRole::FOCAL;
    }

    public function roleLabel(): string
    {
        return $this->role->label();
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn(string $name) => mb_strtoupper(mb_substr($name, 0, 1)))
            ->implode('');
    }

    public function encodedProjectDrafts(): HasMany
    {
        return $this->hasMany(
            ProjectDraft::class,
            'encoded_by'
        );
    }

    public function assignedProjectDrafts(): HasMany
    {
        return $this->hasMany(
            ProjectDraft::class,
            'assigned_tc_id'
        );
    }

    public function reviewedProjectDrafts(): HasMany
    {
        return $this->hasMany(
            ProjectDraft::class,
            'reviewed_by'
        );
    }
}