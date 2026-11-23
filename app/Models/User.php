<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'assigned_province_id',
        'password',
        'must_change_password',
        'password_changed_at',
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
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'has_focal_access' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    /** Form value for "TUPAD Coordinator with Focal access" in User Administration. */
    public const COORDINATOR_FOCAL_ACCESS_CHOICE = 'tc_focal';

    public function assignedProvince(): BelongsTo
    {
        return $this->belongsTo(
            Province::class,
            'assigned_province_id'
        );
    }

    public function requiresProvinceAssignment(): bool
    {
        return $this->isTc();
    }

    public function hasAssignedProvince(): bool
    {
        return $this->assigned_province_id !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isTc(): bool
    {
        return $this->role === UserRole::TC;
    }

    public function isFocal(): bool
    {
        return $this->role === UserRole::FOCAL;
    }

    /** GSIS Enrollment Officer: enrolls TUPAD projects to GSIS GPAI insurance in bulk. */
    public function isGsis(): bool
    {
        return $this->role === UserRole::GSIS;
    }

    /**
     * A TUPAD Coordinator account given the Focal access level: the role is
     * Focal (so isFocal() and every role:focal route apply as-is), but the
     * account is labelled as a TUPAD Coordinator.
     */
    public function isCoordinatorWithFocalAccess(): bool
    {
        return $this->isFocal() && (bool) $this->has_focal_access;
    }

    public function roleLabel(): string
    {
        return $this->isCoordinatorWithFocalAccess()
            ? UserRole::TC->label().' (Focal Access)'
            : $this->role->label();
    }

    /** The account type picked in User Administration: a role value or tc_focal. */
    public function roleChoice(): ?string
    {
        return $this->isCoordinatorWithFocalAccess()
            ? self::COORDINATOR_FOCAL_ACCESS_CHOICE
            : $this->role?->value;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn(string $name) => mb_strtoupper(mb_substr($name, 0, 1)))
            ->implode('');
    }
}
