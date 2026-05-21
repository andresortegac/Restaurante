<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'assigned_user_id',
        'delivery_driver_id',
        'sale_id',
        'delivery_number',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'reference',
        'order_total',
        'delivery_fee',
        'total_charge',
        'customer_payment_amount',
        'change_required',
        'status',
        'scheduled_at',
        'delivered_at',
        'delivery_proof_image_path',
        'notes',
    ];

    protected $casts = [
        'order_total' => 'integer',
        'delivery_fee' => 'integer',
        'total_charge' => 'integer',
        'customer_payment_amount' => 'integer',
        'change_required' => 'integer',
        'scheduled_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected $appends = [
        'delivery_proof_image_url',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function deliveryDriver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function getDeliveryProofImageUrlAttribute(): ?string
    {
        if (! $this->delivery_proof_image_path) {
            return null;
        }

        return route('media.public', ['path' => $this->delivery_proof_image_path], false);
    }

    public static function generateDeliveryNumber(): string
    {
        $datePrefix = now()->format('Ymd');
        $lastId = (int) static::query()->max('id') + 1;

        return 'DOM-' . $datePrefix . '-' . str_pad((string) $lastId, 4, '0', STR_PAD_LEFT);
    }
}
