<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('billing_identification')->nullable()->after('document_number');
            $table->string('identification_document_code')->nullable()->after('billing_identification');
            $table->string('legal_organization_code')->nullable()->after('identification_document_code');
            $table->string('tribute_code')->nullable()->after('legal_organization_code');
            $table->string('municipality_code')->nullable()->after('tribute_code');
            $table->string('billing_address')->nullable()->after('phone');
            $table->string('trade_name')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'billing_identification',
                'identification_document_code',
                'legal_organization_code',
                'tribute_code',
                'municipality_code',
                'billing_address',
                'trade_name',
            ]);
        });
    }
};
