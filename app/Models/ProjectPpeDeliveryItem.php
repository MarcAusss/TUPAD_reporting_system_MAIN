<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPpeDeliveryItem extends Model
{
    protected $fillable = [
        'ppe_delivery_id',
        'ppe_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(
            ProjectPpeDelivery::class,
            'ppe_delivery_id'
        );
    }

    public function ppeItem(): BelongsTo
    {
        return $this->belongsTo(
            ProjectPpeItem::class,
            'ppe_item_id'
        );
    }
}
