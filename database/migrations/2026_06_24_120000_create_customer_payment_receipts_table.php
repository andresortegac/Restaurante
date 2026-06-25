<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('box_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('box_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('box_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('box_impact', 10, 2)->default(0);
            $table->decimal('remaining_pending', 10, 2)->default(0);
            $table->string('reference')->nullable();
            $table->json('allocations')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_receipts');
    }
};
