<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'Efectivo', 'code' => 'CASH', 'description' => 'Pago en efectivo', 'active' => true],
            ['name' => 'Tarjeta de Credito', 'code' => 'CREDIT_CARD', 'description' => 'Tarjeta de Credito', 'active' => false],
            ['name' => 'Tarjeta de Debito', 'code' => 'DEBIT_CARD', 'description' => 'Tarjeta de Debito', 'active' => false],
            ['name' => 'Transferencia Bancaria', 'code' => 'TRANSFER', 'description' => 'Transferencia electronica', 'active' => true],
            ['name' => 'Nequi', 'code' => 'DIGITAL_WALLET', 'description' => 'Pago con Nequi', 'active' => false],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
