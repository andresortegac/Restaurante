<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'box_id',
        'table_order_id',
        'customer_id',
        'customer_name',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'status',
        'payment_status',
        'credit_due_at',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'credit_due_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function tableOrder()
    {
        return $this->belongsTo(TableOrder::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function boxMovements()
    {
        return $this->hasMany(BoxMovement::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function addItem($productId, $quantity, $unitPrice, ?string $productName = null)
    {
        return $this->items()->create([
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
        ]);
    }

    public function calculateTotal()
    {
        $this->loadMissing('items.product.taxRate');

        $this->subtotal = round((float) $this->items->sum('subtotal'), 2);
        $discountAmount = min((float) ($this->discount_amount ?? 0), (float) $this->subtotal);
        $this->discount_amount = round(max(0, $discountAmount), 2);

        $discountFactor = (float) $this->subtotal > 0
            ? max(0, ((float) $this->subtotal - (float) $this->discount_amount) / (float) $this->subtotal)
            : 1.0;

        $taxAmount = $this->items->sum(function ($item) use ($discountFactor): float {
            $subtotal = round((float) $item->subtotal * $discountFactor, 2);
            $taxRate = $item->product?->taxRate;

            if (! $taxRate) {
                return 0.0;
            }

            return $taxRate->calculateTaxAmount($subtotal);
        });

        $taxedTotal = $this->items->sum(function ($item) use ($discountFactor): float {
            $subtotal = round((float) $item->subtotal * $discountFactor, 2);
            $taxRate = $item->product?->taxRate;

            if (! $taxRate) {
                return $subtotal;
            }

            return $taxRate->calculateTotalAmount($subtotal);
        });

        $this->tax_amount = round((float) $taxAmount, 2);
        $this->total = round((float) $taxedTotal, 2);
        $this->save();
    }
}
