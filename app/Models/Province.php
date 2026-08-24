<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(
            Municipality::class
        );
    }

    public function projects(): HasMany
    {
        return $this->hasMany(
            Project::class
        );
    }

    public function projectDrafts(): HasMany
    {
        return $this->hasMany(
            ProjectDraft::class
        );
    }
}