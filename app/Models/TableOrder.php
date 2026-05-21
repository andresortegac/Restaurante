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
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
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
        $this->loadMissing('items.product.taxRate');

        $subtotal = money_value((float) $this->items->sum('subtotal'));
        $taxAmount = money_value(
            $this->items->sum(fn (TableOrderItem $item): float => $this->itemTaxAmount($item)),
        );
        $total = money_value(
            $this->items->sum(fn (TableOrderItem $item): float => $this->itemTotalAmount($item)),
        );

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total = $total;
        $this->save();
    }

    public function splitSummary(): Collection
    {
        $this->loadMissing('items.product.taxRate');

        return $this->items
            ->groupBy(fn (TableOrderItem $item) => (int) ($item->split_group ?: 1))
            ->map(function (Collection $items, int $group): array {
                $groupSubtotal = money_value((float) $items->sum('subtotal'));
                $groupTax = money_value(
                    $items->sum(fn (TableOrderItem $item): float => $this->itemTaxAmount($item)),
                );
                $groupTotal = money_value(
                    $items->sum(fn (TableOrderItem $item): float => $this->itemTotalAmount($item)),
                );

                return [
                    'group' => $group,
                    'items_count' => $items->count(),
                    'subtotal' => $groupSubtotal,
                    'tax_amount' => $groupTax,
                    'total' => $groupTotal,
                ];
            })
            ->sortKeys()
            ->values();
    }

    private function itemTaxAmount(TableOrderItem $item): float
    {
        $subtotal = money_value((float) $item->subtotal);
        $taxRate = $item->product?->taxRate;

        if (! $taxRate) {
            return 0.0;
        }

        return $taxRate->calculateTaxAmount($subtotal);
    }

    private function itemTotalAmount(TableOrderItem $item): float
    {
        $subtotal = money_value((float) $item->subtotal);
        $taxRate = $item->product?->taxRate;

        if (! $taxRate) {
            return $subtotal;
        }

        return $taxRate->calculateTotalAmount($subtotal);
    }

    public static function generateOrderNumber(): string
    {
        $latestOrder = static::latest('id')->first();
        $nextNumber = ($latestOrder?->id ?? 0) + 1;

        return 'PED-' . date('Ym') . '-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
