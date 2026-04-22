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
            ['name' => 'Tarjeta de Credito', 'code' => 'CREDIT_CARD', 'description' => 'Tarjeta de Credito', 'active' => true],
            ['name' => 'Tarjeta de Debito', 'code' => 'DEBIT_CARD', 'description' => 'Tarjeta de Debito', 'active' => true],
            ['name' => 'Transferencia Bancaria', 'code' => 'TRANSFER', 'description' => 'Transferencia electronica', 'active' => true],
            ['name' => 'Billetera Digital', 'code' => 'DIGITAL_WALLET', 'description' => 'Pago con billetera digital', 'active' => true],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}

