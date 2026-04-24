<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electronic_invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('environment')->default('sandbox');
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->unsignedBigInteger('numbering_range_id')->nullable();
            $table->string('document_code')->default('01');
            $table->string('operation_type')->default('10');
            $table->boolean('send_email')->default(true);
            $table->string('default_identification_document_code')->nullable();
            $table->string('default_legal_organization_code')->nullable();
            $table->string('default_tribute_code')->nullable();
            $table->string('default_municipality_code')->nullable();
            $table->string('default_unit_measure_code')->default('94');
            $table->string('default_standard_code')->default('999');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_invoice_settings');
    }
};
