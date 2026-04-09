<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'user_id',
        'opening_balance',
        'closing_balance',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function movements()
    {
        return $this->hasMany(BoxMovement::class);
    }

    public function isOpen()
    {
        return $this->status === 'open';
    }

    public function openBox($initialAmount)
    {
        $this->opening_balance = $initialAmount;
        $this->status = 'open';
        $this->opened_at = now();
        $this->save();
    }

    public function closeBox($closingAmount)
    {
        $this->closing_balance = $closingAmount;
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
