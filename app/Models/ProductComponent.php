<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_product_id',
        'component_product_id',
        'quantity',
        'unit_label',
        'extra_price',
        'is_optional',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'extra_price' => 'decimal:2',
        'is_optional' => 'boolean',
    ];

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
