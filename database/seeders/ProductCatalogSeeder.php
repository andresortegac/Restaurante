<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Bebidas', 'description' => 'Bebidas frias, calientes y refrescos', 'sort_order' => 1],
            ['name' => 'Platos', 'description' => 'Platos principales y opciones del menu', 'sort_order' => 2],
            ['name' => 'Postres', 'description' => 'Postres y acompanamientos dulces', 'sort_order' => 3],
            ['name' => 'Combos', 'description' => 'Paquetes o productos compuestos listos para vender', 'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            ProductCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        $taxRates = [
            [
                'name' => 'Exento',
                'code' => 'EXENTO',
                'description' => 'Productos sin impuesto.',
                'rate' => 0,
                'is_inclusive' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'IVA General',
                'code' => 'IVA-16',
                'description' => 'Impuesto general aplicado a la mayoria de productos.',
                'rate' => 16,
                'is_inclusive' => false,
                'is_default' => true,
                'is_active' => true,
            ],
        ];

        foreach ($taxRates as $taxRate) {
            TaxRate::updateOrCreate(
                ['code' => $taxRate['code']],
                $taxRate
            );
        }
    }
}
