<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'restaurant_table_id',
        'reserved_by',
        'customer_name',
        'customer_phone',
        'customer_email',
        'reservation_at',
        'party_size',
        'status',
        'source',
        'special_requests',
        'notes',
    ];

    protected $casts = [
        'reservation_at' => 'datetime',
        'party_size' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }
}
