<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'sale_id',
        'created_by_user_id',
        'payment_method_id',
        'source_type',
        'source_reference',
        'description',
        'amount',
        'balance',
        'status',
        'due_at',
        'paid_at',
        'paid_reference',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance' => 'integer',
        'due_at' => 'date',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
