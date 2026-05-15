<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('user_role')
                ->whereIn('user_id', function ($query) {
                    $query->select('id')
                        ->from('users')
                        ->whereIn('email', ['cajero@restaurante.com', 'mesero@restaurante.com']);
                })
                ->delete();

            DB::table('users')
                ->whereIn('email', ['cajero@restaurante.com', 'mesero@restaurante.com'])
                ->delete();

            $defaultCategoryIds = DB::table('product_categories')
                ->whereIn('slug', ['bebidas', 'platos', 'postres', 'combos'])
                ->pluck('id');

            if ($defaultCategoryIds->isNotEmpty()) {
                DB::table('products')
                    ->whereIn('category_id', $defaultCategoryIds)
                    ->update([
                        'category_id' => null,
                        'category' => 'Sin categoria',
                    ]);

                DB::table('product_categories')
                    ->whereIn('id', $defaultCategoryIds)
                    ->delete();

                $remainingIds = DB::table('product_categories')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->pluck('id')
                    ->values();

                foreach ($remainingIds as $index => $id) {
                    DB::table('product_categories')
                        ->where('id', $id)
                        ->update(['sort_order' => $index + 1]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se restauran registros demo eliminados.
    }
};
