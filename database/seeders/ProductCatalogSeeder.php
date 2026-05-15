<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
