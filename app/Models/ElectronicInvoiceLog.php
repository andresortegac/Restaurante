<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectronicInvoiceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'level',
        'event',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
