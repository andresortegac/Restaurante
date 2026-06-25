<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'document_number',
        'billing_identification',
        'identification_document_code',
        'legal_organization_code',
        'tribute_code',
        'municipality_code',
        'phone',
        'billing_address',
        'trade_name',
        'email',
        'notes',
        'available_balance',
        'is_active',
    ];

    protected $casts = [
        'available_balance' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tableOrders(): HasMany
    {
        return $this->hasMany(TableOrder::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class);
    }

    public function balanceMovements(): HasMany
    {
        return $this->hasMany(CustomerBalanceMovement::class);
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(CustomerPaymentReceipt::class);
    }

    public function pendingCredits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class)->where('status', 'pending');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
