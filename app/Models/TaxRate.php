<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'rate',
        'is_inclusive',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_inclusive' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function calculateTaxAmount(float $amount): float
    {
        $amount = round($amount, 2);
        $rate = (float) $this->rate;

        if (! $this->is_active || $rate <= 0 || $amount <= 0) {
            return 0.0;
        }

        if ($this->is_inclusive) {
            return round($amount - ($amount / (1 + ($rate / 100))), 2);
        }

        return round($amount * ($rate / 100), 2);
    }

    public function calculateTotalAmount(float $amount): float
    {
        $amount = round($amount, 2);

        if (! $this->is_active || (float) $this->rate <= 0) {
            return $amount;
        }

        if ($this->is_inclusive) {
            return $amount;
        }

        return round($amount + $this->calculateTaxAmount($amount), 2);
    }
}
