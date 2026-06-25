<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'customer_id',
        'box_id',
        'box_session_id',
        'box_movement_id',
        'payment_method_id',
        'received_by_user_id',
        'amount',
        'box_impact',
        'remaining_pending',
        'reference',
        'allocations',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'box_impact' => 'integer',
        'remaining_pending' => 'integer',
        'allocations' => 'array',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function boxSession(): BelongsTo
    {
        return $this->belongsTo(BoxSession::class);
    }

    public function boxMovement(): BelongsTo
    {
        return $this->belongsTo(BoxMovement::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
