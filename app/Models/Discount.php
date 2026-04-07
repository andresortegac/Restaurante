<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function coupons()
    {
        return $this->hasMany(PromotionCoupon::class);
    }

    public function isActive()
    {
        $now = now();
        return $this->active &&
               (!$this->starts_at || $now >= $this->starts_at) &&
               (!$this->ends_at || $now <= $this->ends_at);
    }

    public function calculateDiscountAmount($amount)
    {
        if ($this->type === 'percentage') {
            return ($amount * $this->value) / 100;
        }
        return min($this->value, $amount);
    }
}
