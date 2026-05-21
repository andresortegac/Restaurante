<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'payment_method_id',
        'amount',
        'received_amount',
        'change_amount',
        'tip_amount',
        'reference',
        'status',
    ];

    protected $casts = [
        'amount' => 'integer',
        'received_amount' => 'integer',
        'change_amount' => 'integer',
        'tip_amount' => 'integer',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function boxMovement()
    {
        return $this->hasOne(BoxMovement::class);
    }
}
