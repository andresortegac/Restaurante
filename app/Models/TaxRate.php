<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'rate',
        'is_inclusive',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_inclusive' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
