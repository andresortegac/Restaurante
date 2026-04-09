<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('table_order_id')
                ->nullable()
                ->after('box_id')
                ->constrained('table_orders')
                ->nullOnDelete();
            $table->string('customer_name')->nullable()->after('table_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_order_id');
            $table->dropColumn('customer_name');
        });
    }
};
