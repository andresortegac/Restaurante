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
        'customer_name',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
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
        $this->subtotal = $this->items->sum('subtotal');
        $discountAmount = min((float) ($this->discount_amount ?? 0), (float) $this->subtotal);
        $this->discount_amount = max(0, $discountAmount);
        $this->tax_amount = ($this->subtotal - $this->discount_amount) * 0.16;
        $this->total = $this->subtotal - $this->discount_amount + $this->tax_amount;
        $this->save();
    }
}
