<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Box extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'user_id',
        'closed_by_user_id',
        'opening_balance',
        'opening_notes',
        'closing_balance',
        'counted_balance',
        'difference_amount',
        'closing_notes',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BoxMovement::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BoxSession::class);
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(BoxSession::class)->where('status', 'open')->latestOfMany('opened_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BoxAuditLog::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function openBox($initialAmount)
    {
        $this->opening_balance = $initialAmount;
        $this->opening_notes = null;
        $this->status = 'open';
        $this->closing_balance = null;
        $this->counted_balance = null;
        $this->difference_amount = null;
        $this->closing_notes = null;
        $this->closed_by_user_id = null;
        $this->opened_at = now();
        $this->closed_at = null;
        $this->save();
    }

    public function closeBox($closingAmount)
    {
        $this->closing_balance = $closingAmount;
        $this->counted_balance = $closingAmount;
        $this->status = 'closed';
        $this->closed_at = now();
        $this->save();
    }

    public function currentBalance(): float
    {
        $movementTotal = (float) $this->movements()->sum('amount');

        return (float) $this->opening_balance + $movementTotal;
    }
}
