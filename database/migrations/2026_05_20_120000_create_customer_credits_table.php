<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete()->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 50)->default('manual_assignment');
            $table->string('source_reference')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_reference')->nullable();
            $table->timestamps();
        });

        $sales = DB::table('sales')
            ->where('payment_status', 'credit')
            ->whereNotNull('customer_id')
            ->select([
                'id',
                'customer_id',
                'user_id',
                'table_order_id',
                'total',
                'credit_due_at',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->get();

        foreach ($sales as $sale) {
            $notes = (string) ($sale->notes ?? '');
            $sourceType = $sale->table_order_id ? 'table_order' : 'manual_charge';

            DB::table('customer_credits')->updateOrInsert(
                ['sale_id' => $sale->id],
                [
                    'customer_id' => $sale->customer_id,
                    'created_by_user_id' => $sale->user_id,
                    'payment_method_id' => null,
                    'source_type' => $sourceType,
                    'source_reference' => $sale->table_order_id ? 'Pedido asociado' : 'Cobro manual',
                    'description' => $notes !== '' ? $notes : 'Saldo generado por la venta #' . $sale->id,
                    'amount' => round((float) $sale->total, 2),
                    'status' => 'pending',
                    'due_at' => $sale->credit_due_at,
                    'paid_at' => null,
                    'paid_reference' => null,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->updated_at,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
