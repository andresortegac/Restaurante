<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('received_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('change_amount', 10, 2)->default(0)->after('received_amount');
            $table->decimal('tip_amount', 10, 2)->default(0)->after('change_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'received_amount',
                'change_amount',
                'tip_amount',
            ]);
        });
    }
};
