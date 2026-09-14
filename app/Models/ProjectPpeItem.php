<?php

namespace App\Models;

use App\Enums\PpeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectPpeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'ppe_type',
        'product',
        'beneficiary_count',
        'quantity',
        'unit_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'ppe_type' => PpeType::class,
            'quantity' => 'integer',
            'unit_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(
            ProjectPpeDeliveryItem::class,
            'ppe_item_id'
        );
    }

    /**
     * Units already recorded across every delivery receipt for this item.
     */
    public function deliveredQuantity(): int
    {
        return (int) $this->deliveryItems()->sum('quantity');
    }

    /**
     * Total units planned for this item: Beneficiaries x Quantity per
     * beneficiary (Quantity is 1 for Short-Term projects).
     */
    public function plannedQuantity(): int
    {
        return (int) $this->beneficiary_count * max(1, (int) $this->quantity);
    }

    /**
     * How many more units can still be delivered before exceeding what was
     * planned for this item.
     */
    public function remainingDeliverableQuantity(): int
    {
        return max(0, $this->plannedQuantity() - $this->deliveredQuantity());
    }
}