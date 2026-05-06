<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryDriver extends Model
{
    use HasFactory;

    public const VEHICLE_TYPES = [
        'carro' => 'Carro',
        'moto' => 'Moto',
        'bicicleta' => 'Bicicleta',
    ];

    protected $fillable = [
        'name',
        'document_number',
        'phone',
        'email',
        'address',
        'photo_path',
        'vehicle_type',
        'vehicle_plate',
        'vehicle_model',
        'vehicle_color',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'delivery_driver_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return route('media.public', ['path' => $this->photo_path], false);
    }

    public function getVehicleTypeLabelAttribute(): string
    {
        $normalizedVehicleType = strtolower((string) $this->vehicle_type);

        return self::VEHICLE_TYPES[$normalizedVehicleType] ?? ucfirst((string) $this->vehicle_type);
    }
}
