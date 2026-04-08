<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'category',
        'category_id',
        'tax_rate_id',
        'product_type',
        'sku',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'category_id' => 'integer',
        'tax_rate_id' => 'integer',
        'active' => 'boolean',
    ];

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'parent_product_id');
    }

    public function usedInCombos(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'component_product_id');
    }

    public function isInStock($quantity = 1)
    {
        return $this->stock >= $quantity;
    }

    public function reduceStock($quantity)
    {
        $this->stock -= $quantity;
        $this->save();
    }

    public function increaseStock($quantity)
    {
        $this->stock += $quantity;
        $this->save();
    }
}
