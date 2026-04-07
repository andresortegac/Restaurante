<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Agua Mineral', 'description' => 'Botella de 500ml', 'price' => 15.00, 'stock' => 100, 'category' => 'Bebidas', 'sku' => 'AGU-001', 'active' => true],
            ['name' => 'Refresco Cola', 'description' => 'Bebida gaseosa 350ml', 'price' => 25.00, 'stock' => 100, 'category' => 'Bebidas', 'sku' => 'REF-001', 'active' => true],
            ['name' => 'Café Americano', 'description' => 'Café negro', 'price' => 45.00, 'stock' => 50, 'category' => 'Bebidas', 'sku' => 'CAF-001', 'active' => true],
            ['name' => 'Hamburguesa Simple', 'description' => 'Carne de res con queso', 'price' => 85.00, 'stock' => 50, 'category' => 'Platos', 'sku' => 'HAM-001', 'active' => true],
            ['name' => 'Pizza Mediana', 'description' => 'Pizza con 6 porciones', 'price' => 150.00, 'stock' => 30, 'category' => 'Platos', 'sku' => 'PIZ-001', 'active' => true],
            ['name' => 'Pechuga de Pollo', 'description' => 'Pechuga a la plancha', 'price' => 130.00, 'stock' => 40, 'category' => 'Platos', 'sku' => 'POL-001', 'active' => true],
            ['name' => 'Flan', 'description' => 'Flan casero', 'price' => 45.00, 'stock' => 30, 'category' => 'Postres', 'sku' => 'POS-001', 'active' => true],
            ['name' => 'Helado', 'description' => 'Bola de helado', 'price' => 35.00, 'stock' => 60, 'category' => 'Postres', 'sku' => 'POS-002', 'active' => true],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
