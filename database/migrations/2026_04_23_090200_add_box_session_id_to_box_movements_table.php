<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('box_movements', function (Blueprint $table) {
            $table->foreignId('box_session_id')->nullable()->after('box_id')->constrained('box_sessions')->nullOnDelete();
        });

        $sessions = DB::table('box_sessions')->get();

        foreach ($sessions as $session) {
            DB::table('box_movements')
                ->where('box_id', $session->box_id)
                ->when($session->opened_at, fn ($query) => $query->where('occurred_at', '>=', $session->opened_at))
                ->when($session->closed_at, fn ($query) => $query->where('occurred_at', '<=', $session->closed_at))
                ->whereNull('box_session_id')
                ->update(['box_session_id' => $session->id]);
        }
    }

    public function down(): void
    {
        Schema::table('box_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('box_session_id');
        });
    }
};
