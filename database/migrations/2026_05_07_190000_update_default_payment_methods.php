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
        DB::table('payment_methods')
            ->where('code', 'DIGITAL_WALLET')
            ->update([
                'name' => 'Nequi',
                'description' => 'Pago con Nequi',
                'active' => true,
                'updated_at' => now(),
            ]);

        DB::table('payment_methods')
            ->where('code', 'DEBIT_CARD')
            ->update([
                'active' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payment_methods')
            ->where('code', 'DIGITAL_WALLET')
            ->update([
                'name' => 'Billetera Digital',
                'description' => 'Pago con billetera digital',
                'updated_at' => now(),
            ]);

        DB::table('payment_methods')
            ->where('code', 'DEBIT_CARD')
            ->update([
                'active' => true,
                'updated_at' => now(),
            ]);
    }
};
