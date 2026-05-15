<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('products')
            ->whereNull('sku')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $product): void {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'sku' => 'SKU-' . str_pad((string) $product->id, 6, '0', STR_PAD_LEFT),
                    ]);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable(false)->change();
        });
    }
};
