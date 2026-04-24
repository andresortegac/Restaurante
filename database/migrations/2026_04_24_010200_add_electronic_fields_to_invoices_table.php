<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('invoice_type');
            $table->string('reference_code')->nullable()->after('provider');
            $table->string('electronic_number')->nullable()->after('reference_code');
            $table->string('cufe')->nullable()->after('electronic_number');
            $table->string('status_message')->nullable()->after('status');
            $table->json('validation_errors')->nullable()->after('status_message');
            $table->string('xml_path')->nullable()->after('validation_errors');
            $table->string('pdf_path')->nullable()->after('xml_path');
            $table->string('public_url')->nullable()->after('pdf_path');
            $table->string('qr_url')->nullable()->after('public_url');
            $table->json('factus_payload')->nullable()->after('qr_url');
            $table->json('factus_response')->nullable()->after('factus_payload');
            $table->unsignedInteger('retry_count')->default(0)->after('factus_response');
            $table->timestamp('last_attempt_at')->nullable()->after('retry_count');
            $table->timestamp('last_error_at')->nullable()->after('last_attempt_at');
            $table->timestamp('sent_at')->nullable()->after('last_error_at');
            $table->timestamp('synced_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'reference_code',
                'electronic_number',
                'cufe',
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
            ]);
        });
    }
};
