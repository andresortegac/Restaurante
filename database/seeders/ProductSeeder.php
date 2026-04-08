<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductComponent;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTax = TaxRate::where('is_default', true)->first();

        $products = [
            ['name' => 'Agua Mineral', 'description' => 'Botella de 500ml', 'price' => 15.00, 'stock' => 100, 'category' => 'Bebidas', 'sku' => 'AGU-001', 'active' => true, 'tracks_stock' => true],
            ['name' => 'Refresco Cola', 'description' => 'Bebida gaseosa 350ml', 'price' => 25.00, 'stock' => 100, 'category' => 'Bebidas', 'sku' => 'REF-001', 'active' => true, 'tracks_stock' => true],
            ['name' => 'Cafe Americano', 'description' => 'Cafe negro', 'price' => 45.00, 'stock' => 0, 'category' => 'Bebidas', 'sku' => 'CAF-001', 'active' => true, 'tracks_stock' => false],
            ['name' => 'Hamburguesa Simple', 'description' => 'Carne de res con queso', 'price' => 85.00, 'stock' => 0, 'category' => 'Platos', 'sku' => 'HAM-001', 'active' => true, 'tracks_stock' => false],
            ['name' => 'Pizza Mediana', 'description' => 'Pizza con 6 porciones', 'price' => 150.00, 'stock' => 0, 'category' => 'Platos', 'sku' => 'PIZ-001', 'active' => true, 'tracks_stock' => false],
            ['name' => 'Pechuga de Pollo', 'description' => 'Pechuga a la plancha', 'price' => 130.00, 'stock' => 0, 'category' => 'Platos', 'sku' => 'POL-001', 'active' => true, 'tracks_stock' => false],
            ['name' => 'Flan', 'description' => 'Flan casero', 'price' => 45.00, 'stock' => 0, 'category' => 'Postres', 'sku' => 'POS-001', 'active' => true, 'tracks_stock' => false],
            ['name' => 'Helado', 'description' => 'Bola de helado', 'price' => 35.00, 'stock' => 0, 'category' => 'Postres', 'sku' => 'POS-002', 'active' => true, 'tracks_stock' => false],
            ['name' => 'Combo Ejecutivo', 'description' => 'Combo base con plato fuerte, bebida y postre', 'price' => 150.00, 'stock' => 0, 'category' => 'Combos', 'sku' => 'COM-001', 'active' => false, 'product_type' => 'combo', 'tracks_stock' => false],
        ];

        foreach ($products as $product) {
            $category = ProductCategory::where('name', $product['category'])->first();

            Product::updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'tracks_stock' => $product['tracks_stock'] ?? false,
                    'category' => $product['category'],
                    'category_id' => $category?->id,
                    'tax_rate_id' => $defaultTax?->id,
                    'product_type' => $product['product_type'] ?? 'simple',
                    'active' => $product['active'],
                ]
            );
        }

        $combo = Product::where('sku', 'COM-001')->first();
        $hamburger = Product::where('sku', 'HAM-001')->first();
        $water = Product::where('sku', 'AGU-001')->first();
        $flan = Product::where('sku', 'POS-001')->first();

        if ($combo && $hamburger && $water && $flan) {
            $components = [
                ['product' => $hamburger, 'quantity' => 1, 'unit_label' => 'unidad'],
                ['product' => $water, 'quantity' => 1, 'unit_label' => 'botella'],
                ['product' => $flan, 'quantity' => 1, 'unit_label' => 'porcion'],
            ];

            foreach ($components as $component) {
                ProductComponent::updateOrCreate(
                    [
                        'parent_product_id' => $combo->id,
                        'component_product_id' => $component['product']->id,
                    ],
                    [
                        'quantity' => $component['quantity'],
                        'unit_label' => $component['unit_label'],
                        'extra_price' => 0,
                        'is_optional' => false,
                    ]
                );
            }
        }
    }
}
