<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
        'voided_by_user_id',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'credit_due_at' => 'date',
        'voided_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
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

    public function isVoided(): bool
    {
        return $this->status === 'voided' || $this->voided_at !== null;
    }

    public function canBeEditedInOpenCashSession(): bool
    {
        if ($this->isVoided() || $this->invoice?->isVoided()) {
            return false;
        }

        $movements = $this->relationLoaded('boxMovements')
            ? $this->boxMovements
            : $this->boxMovements()->with('session')->get();

        if ($movements->isEmpty()) {
            return false;
        }

        return $movements->every(fn (BoxMovement $movement): bool => $movement->session?->status === 'open');
    }

    public function customerCredit()
    {
        return $this->hasOne(CustomerCredit::class);
    }

    public function customerBalanceMovements()
    {
        return $this->hasMany(CustomerBalanceMovement::class);
    }

    public function customerBalanceAppliedTotal(): float
    {
        $movements = $this->relationLoaded('customerBalanceMovements')
            ? $this->customerBalanceMovements
            : $this->customerBalanceMovements()->where('movement_type', 'sale_consumption')->get();

        return money_value(abs((float) $movements
            ->where('movement_type', 'sale_consumption')
            ->sum('amount')));
    }

    public function paymentMethodSummary(): string
    {
        $labels = $this->paymentLabels();

        return $labels->join(', ');
    }

    public function externalReceivedTotal(): float
    {
        return money_value((float) $this->payments->sum('received_amount'));
    }

    public function paymentChangeTotal(): float
    {
        return money_value((float) $this->payments->sum('change_amount'));
    }

    public function paymentTipTotal(): float
    {
        return money_value((float) $this->payments->sum('tip_amount'));
    }

    private function paymentLabels(): Collection
    {
        $labels = $this->payments
            ->map(fn ($payment) => $payment->paymentMethod?->name)
            ->filter()
            ->unique()
            ->values();

        if ($this->customerBalanceAppliedTotal() > 0) {
            $labels->prepend('Saldo a favor');
        }

        return $labels
            ->filter()
            ->unique()
            ->values();
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

        $this->subtotal = money_value((float) $this->items->sum('subtotal'));
        $discountAmount = min((float) ($this->discount_amount ?? 0), (float) $this->subtotal);
        $this->discount_amount = money_value(max(0, $discountAmount));

        $discountFactor = (float) $this->subtotal > 0
            ? max(0, ((float) $this->subtotal - (float) $this->discount_amount) / (float) $this->subtotal)
            : 1.0;

        $taxAmount = $this->items->sum(function ($item) use ($discountFactor): float {
            $subtotal = money_value((float) $item->subtotal * $discountFactor);
            $taxRate = $item->product?->taxRate;

            if (! $taxRate) {
                return 0.0;
            }

            return $taxRate->calculateTaxAmount($subtotal);
        });

        $taxedTotal = $this->items->sum(function ($item) use ($discountFactor): float {
            $subtotal = money_value((float) $item->subtotal * $discountFactor);
            $taxRate = $item->product?->taxRate;

            if (! $taxRate) {
                return $subtotal;
            }

            return $taxRate->calculateTotalAmount($subtotal);
        });

        $this->tax_amount = money_value((float) $taxAmount);
        $this->total = money_value((float) $taxedTotal);
        $this->save();
    }
}
