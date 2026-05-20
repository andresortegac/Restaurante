<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_credits', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->after('amount');
        });

        DB::table('customer_credits')->update([
            'balance' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('customer_credits', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
