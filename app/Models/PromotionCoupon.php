<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_id',
        'code',
        'usage_limit',
        'usage_count',
        'min_purchase_amount',
        'expires_at',
        'active',
    ];

    protected $casts = [
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'min_purchase_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function isValid()
    {
        if (!$this->active) {
            return false;
        }
        if ($this->expires_at && now() > $this->expires_at) {
            return false;
        }
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }

    public function canBeUsed($purchaseAmount = 0)
    {
        if (!$this->isValid()) {
            return false;
        }
        if ($this->min_purchase_amount && $purchaseAmount < $this->min_purchase_amount) {
            return false;
        }
        return true;
    }

    public function use()
    {
        $this->usage_count++;
        $this->save();
    }
}
