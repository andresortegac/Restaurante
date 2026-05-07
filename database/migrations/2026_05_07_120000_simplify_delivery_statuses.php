<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('deliveries')
            ->whereIn('status', ['pending', 'assigned', 'in_transit'])
            ->update(['status' => 'active']);

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('status', 50)->default('active')->change();
        });
    }

    public function down(): void
    {
        DB::table('deliveries')
            ->where('status', 'active')
            ->update(['status' => 'pending']);

        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('status', 50)->default('pending')->change();
        });
    }
};
