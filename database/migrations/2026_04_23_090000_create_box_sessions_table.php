<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('box_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('counted_balance', 10, 2)->nullable();
            $table->decimal('difference_amount', 10, 2)->nullable();
            $table->text('closing_notes')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        $boxes = DB::table('boxes')
            ->whereNotNull('opened_at')
            ->get();

        foreach ($boxes as $box) {
            DB::table('box_sessions')->insert([
                'box_id' => $box->id,
                'user_id' => $box->user_id,
                'opening_balance' => $box->opening_balance ?? 0,
                'status' => $box->status === 'closed' ? 'closed' : 'open',
                'counted_balance' => $box->closing_balance,
                'difference_amount' => null,
                'closing_notes' => null,
                'closed_by_user_id' => $box->status === 'closed' ? $box->user_id : null,
                'opened_at' => $box->opened_at,
                'closed_at' => $box->closed_at,
                'created_at' => $box->created_at ?? now(),
                'updated_at' => $box->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('box_sessions');
    }
};
