<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'area',
        'capacity',
        'status',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(TableOrder::class);
    }

    public function openOrder(): HasOne
    {
        return $this->hasOne(TableOrder::class)
            ->where('status', 'open')
            ->latestOfMany();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
