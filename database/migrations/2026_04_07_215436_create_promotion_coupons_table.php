<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotion_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_coupons');
    }
};
