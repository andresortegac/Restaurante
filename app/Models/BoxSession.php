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
        'opening_balance' => 'integer',
        'counted_balance' => 'integer',
        'difference_amount' => 'integer',
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
        return money_value((float) $this->opening_balance + (float) $this->movements()->sum('amount'));
    }

    public function incomeTotal(): float
    {
        return money_value((float) $this->movements()->where('amount', '>', 0)->sum('amount'));
    }

    public function expenseTotal(): float
    {
        return money_value(abs((float) $this->movements()->where('amount', '<', 0)->sum('amount')));
    }
}
