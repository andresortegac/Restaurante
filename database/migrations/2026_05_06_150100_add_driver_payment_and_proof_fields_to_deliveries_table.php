<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('delivery_driver_id')->nullable()->after('customer_id')->constrained('delivery_drivers')->nullOnDelete();
            $table->decimal('customer_payment_amount', 10, 2)->default(0)->after('total_charge');
            $table->decimal('change_required', 10, 2)->default(0)->after('customer_payment_amount');
            $table->string('delivery_proof_image_path')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_driver_id');
            $table->dropColumn([
                'customer_payment_amount',
                'change_required',
                'delivery_proof_image_path',
            ]);
        });
    }
};
