<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'invoice_number',
        'invoice_type',
        'xml_content',
        'pdf_content',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function generateInvoiceNumber()
    {
        $latestInvoice = static::latest('id')->first();
        $nextNumber = ($latestInvoice?->id ?? 0) + 1;
        return 'INV-' . date('Ym') . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
