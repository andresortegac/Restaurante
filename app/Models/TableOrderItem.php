<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_order_id',
        'product_id',
        'product_name',
        'unit_price',
        'quantity',
        'subtotal',
        'split_group',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
        'split_group' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class, 'table_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
