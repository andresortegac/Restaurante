<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')
                ->nullable()
                ->after('category');
            $table->unsignedBigInteger('tax_rate_id')
                ->nullable()
                ->after('category_id');
            $table->string('product_type')
                ->default('simple')
                ->after('tax_rate_id');

            $table->index('category_id');
            $table->index('tax_rate_id');
        });

        $existingCategories = DB::table('products')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter();

        foreach ($existingCategories as $index => $categoryName) {
            $slug = Str::slug($categoryName);

            $categoryId = DB::table('product_categories')
                ->where('slug', $slug)
                ->value('id');

            if (!$categoryId) {
                $categoryId = DB::table('product_categories')->insertGetId([
                    'name' => $categoryName,
                    'slug' => $slug ?: 'categoria-' . ($index + 1),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('products')
                ->where('category', $categoryName)
                ->update(['category_id' => $categoryId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tax_rate_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn(['tax_rate_id', 'category_id']);
            $table->dropColumn('product_type');
        });
    }
};
