<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'box_id',
        'box_session_id',
        'sale_id',
        'payment_id',
        'user_id',
        'movement_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BoxSession::class, 'box_session_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
