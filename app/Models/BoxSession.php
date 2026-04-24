<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoxSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'box_id',
        'user_id',
        'opening_balance',
        'opening_notes',
        'status',
        'counted_balance',
        'difference_amount',
        'closing_notes',
        'closed_by_user_id',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BoxMovement::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BoxAuditLog::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function currentBalance(): float
    {
        return round((float) $this->opening_balance + (float) $this->movements()->sum('amount'), 2);
    }

    public function incomeTotal(): float
    {
        return round((float) $this->movements()->where('amount', '>', 0)->sum('amount'), 2);
    }

    public function expenseTotal(): float
    {
        return round(abs((float) $this->movements()->where('amount', '<', 0)->sum('amount')), 2);
    }
}
