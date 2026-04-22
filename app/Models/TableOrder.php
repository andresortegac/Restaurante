<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class TableOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_table_id',
        'transferred_from_table_id',
        'order_number',
        'customer_id',
        'customer_name',
        'status',
        'opened_by_user_id',
        'subtotal',
        'tax_amount',
        'total',
        'notes',
        'last_transferred_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'last_transferred_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function previousTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'transferred_from_table_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TableOrderItem::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function recalculateTotals(): void
    {
        $this->loadMissing('items');

        $subtotal = (float) $this->items->sum('subtotal');
        $taxAmount = $subtotal * 0.16;

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total = $subtotal + $taxAmount;
        $this->save();
    }

    public function splitSummary(): Collection
    {
        $this->loadMissing('items');

        $subtotal = (float) $this->subtotal;
        $taxAmount = (float) $this->tax_amount;

        return $this->items
            ->groupBy(fn (TableOrderItem $item) => (int) ($item->split_group ?: 1))
            ->map(function (Collection $items, int $group) use ($subtotal, $taxAmount): array {
                $groupSubtotal = (float) $items->sum('subtotal');
                $groupTax = $subtotal > 0 ? ($groupSubtotal / $subtotal) * $taxAmount : 0;

                return [
                    'group' => $group,
                    'items_count' => $items->count(),
                    'subtotal' => $groupSubtotal,
                    'tax_amount' => $groupTax,
                    'total' => $groupSubtotal + $groupTax,
                ];
            })
            ->sortKeys()
            ->values();
    }

    public static function generateOrderNumber(): string
    {
        $latestOrder = static::latest('id')->first();
        $nextNumber = ($latestOrder?->id ?? 0) + 1;

        return 'PED-' . date('Ym') . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
