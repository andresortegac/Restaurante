<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const TYPE_TICKET = 'ticket';
    public const TYPE_ELECTRONIC = 'electronic';

    protected $fillable = [
        'sale_id',
        'invoice_number',
        'invoice_type',
        'provider',
        'reference_code',
        'electronic_number',
        'cufe',
        'xml_content',
        'pdf_content',
        'status',
        'status_message',
        'validation_errors',
        'xml_path',
        'pdf_path',
        'public_url',
        'qr_url',
        'factus_payload',
        'factus_response',
        'retry_count',
        'last_attempt_at',
        'last_error_at',
        'sent_at',
        'synced_at',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'validation_errors' => 'array',
        'factus_payload' => 'array',
        'factus_response' => 'array',
        'last_attempt_at' => 'datetime',
        'last_error_at' => 'datetime',
        'sent_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ElectronicInvoiceLog::class);
    }

    public function generateInvoiceNumber(string $prefix = 'INV')
    {
        $latestInvoice = static::latest('id')->first();
        $nextNumber = ($latestInvoice?->id ?? 0) + 1;

        return strtoupper($prefix) . '-' . date('Ym') . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['validated', 'submitted'], true);
    }

    public function isElectronic(): bool
    {
        return $this->invoice_type === self::TYPE_ELECTRONIC;
    }

    public function isTicket(): bool
    {
        return $this->invoice_type === self::TYPE_TICKET;
    }
}
