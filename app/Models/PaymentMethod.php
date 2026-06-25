<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    public const SYSTEM_ALLOWED_CODES = ['CASH', 'TRANSFER'];

    protected $fillable = [
        'name',
        'code',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeSystemAllowed(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->whereIn('code', self::SYSTEM_ALLOWED_CODES);
    }
}
