<?php

use App\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')
            ->whereIn('code', PaymentMethod::SYSTEM_ALLOWED_CODES)
            ->update(['active' => true]);

        DB::table('payment_methods')
            ->whereNotIn('code', PaymentMethod::SYSTEM_ALLOWED_CODES)
            ->update(['active' => false]);
    }

    public function down(): void
    {
        DB::table('payment_methods')
            ->whereIn('code', ['CREDIT_CARD', 'DIGITAL_WALLET'])
            ->update(['active' => true]);
    }
};
