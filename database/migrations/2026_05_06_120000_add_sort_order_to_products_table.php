<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('product_type');
        });

        DB::table('products')
            ->select(['id', 'category_id', 'name'])
            ->orderBy('category_id')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (object $product): string => (string) ($product->category_id ?? 'uncategorized'))
            ->each(function (Collection $products): void {
                foreach ($products->values() as $index => $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['sort_order' => $index + 1]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
