<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_enabled',
        'environment',
        'client_id',
        'client_secret',
        'username',
        'password',
        'numbering_range_id',
        'document_code',
        'operation_type',
        'send_email',
        'default_identification_document_code',
        'default_legal_organization_code',
        'default_tribute_code',
        'default_municipality_code',
        'default_unit_measure_code',
        'default_standard_code',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'send_email' => 'boolean',
        'client_secret' => 'encrypted',
        'password' => 'encrypted',
        'numbering_range_id' => 'integer',
    ];

    public function baseUrl(): string
    {
        return $this->environment === 'production'
            ? config('factus.production_base_url')
            : config('factus.sandbox_base_url');
    }
}
