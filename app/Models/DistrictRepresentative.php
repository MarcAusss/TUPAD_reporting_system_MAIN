<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistrictRepresentative extends Model
{
    protected $fillable = [
        'province',
        'district',
        'representative',
        'sort_order',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Normalizes district labels so "2nd" and "2nd District" match.
     */
    public static function districtKey(?string $district): string
    {
        return strtolower(trim((string) preg_replace('/\s*district\s*$/i', '', (string) $district)));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
