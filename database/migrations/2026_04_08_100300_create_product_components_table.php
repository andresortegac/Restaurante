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
        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit_label')->nullable();
            $table->decimal('extra_price', 10, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->unique(['parent_product_id', 'component_product_id'], 'product_components_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
