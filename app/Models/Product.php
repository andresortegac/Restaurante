<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'category',
        'sku',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'active' => 'boolean',
    ];

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
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
