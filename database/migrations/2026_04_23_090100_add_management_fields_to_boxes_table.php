<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->text('opening_notes')->nullable()->after('opening_balance');
            $table->decimal('counted_balance', 10, 2)->nullable()->after('closing_balance');
            $table->decimal('difference_amount', 10, 2)->nullable()->after('counted_balance');
            $table->text('closing_notes')->nullable()->after('difference_amount');
            $table->foreignId('closed_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropColumn([
                'opening_notes',
                'counted_balance',
                'difference_amount',
                'closing_notes',
            ]);
        });
    }
};
