<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('tracks_stock')->default(true)->after('stock');
        });

        DB::table('products')
            ->where('product_type', 'combo')
            ->update(['tracks_stock' => false]);

        DB::table('products')
            ->where(function ($query) {
                $query->whereRaw("lower(coalesce(category, '')) like '%plato%'")
                    ->orWhereRaw("lower(coalesce(category, '')) like '%comida%'");
            })
            ->update(['tracks_stock' => false]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tracks_stock');
        });
    }
};
