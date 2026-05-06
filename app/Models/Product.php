<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'tracks_stock',
        'category',
        'category_id',
        'tax_rate_id',
        'product_type',
        'sort_order',
        'sku',
        'image_path',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'tracks_stock' => 'boolean',
        'category_id' => 'integer',
        'tax_rate_id' => 'integer',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
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

    public function scopeSellable(Builder $query): Builder
    {
        return $query
            ->where('products.active', true)
            ->where(function (Builder $nestedQuery) {
                $nestedQuery->whereIn('products.product_type', ['simple', 'combo'])
                    ->orWhereNull('products.product_type');
            });
    }

    public function scopeVisibleInMenu(Builder $query): Builder
    {
        return $query
            ->sellable()
            ->where(function (Builder $nestedQuery) {
                $nestedQuery->whereNull('products.category_id')
                    ->orWhereHas('menuCategory', fn (Builder $categoryQuery) => $categoryQuery->where('is_active', true));
            });
    }

    public function scopeOrderedForMenu(Builder $query): Builder
    {
        return $query
            ->leftJoin('product_categories as menu_categories', 'products.category_id', '=', 'menu_categories.id')
            ->select('products.*')
            ->orderByRaw('CASE WHEN menu_categories.sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('menu_categories.sort_order')
            ->orderBy('products.sort_order')
            ->orderBy('products.name');
    }

    public function isInStock($quantity = 1)
    {
        if (! $this->tracks_stock) {
            return true;
        }

        return $this->stock >= $quantity;
    }

    public function reduceStock($quantity)
    {
        if (! $this->tracks_stock) {
            return;
        }

        $this->stock -= $quantity;
        $this->save();
    }

    public function increaseStock($quantity)
    {
        if (! $this->tracks_stock) {
            return;
        }

        $this->stock += $quantity;
        $this->save();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
