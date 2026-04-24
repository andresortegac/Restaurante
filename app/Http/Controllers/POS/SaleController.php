<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\BoxAuditLog;
use App\Models\BoxMovement;
use App\Models\BoxSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with('items', 'payments')->latest()->paginate(20);
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'box_id' => 'required|exists:boxes,id',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $box = Box::query()->lockForUpdate()->findOrFail($validated['box_id']);
            $session = BoxSession::query()
                ->where('box_id', $box->id)
                ->where('status', 'open')
                ->orderByDesc('opened_at')
                ->lockForUpdate()
                ->first();

            if (! $box->isOpen()) {
                throw ValidationException::withMessages([
                    'box_id' => 'La caja seleccionada no esta abierta.',
                ]);
            }

            if (! $session) {
                $session = BoxSession::query()->create([
                    'box_id' => $box->id,
                    'user_id' => $box->user_id ?? Auth::id(),
                    'opening_balance' => (float) $box->opening_balance,
                    'status' => 'open',
                    'opened_at' => $box->opened_at ?? now(),
                ]);
            }

            $sale = Sale::create([
                'user_id' => Auth::id(),
                'box_id' => $validated['box_id'],
                'status' => 'completed',
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($item['product_id']);

                $productType = $product->product_type ?: 'simple';

                if (! in_array($productType, ['simple', 'combo'], true) || ! $product->active) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos ya no esta disponible en el POS.',
                    ]);
                }

                if (! $product->isInStock($item['quantity'])) {
                    throw ValidationException::withMessages([
                        'items' => 'Stock insuficiente para uno de los productos seleccionados.',
                    ]);
                }

                $sale->addItem($item['product_id'], $item['quantity'], $item['unit_price'], $product->name);
                $product->reduceStock($item['quantity']);
            }

            $sale->load('items');
            $sale->calculateTotal();

            if (! empty($validated['payment_method_id'])) {
                $paymentMethod = PaymentMethod::query()->findOrFail($validated['payment_method_id']);

                $payment = $sale->payments()->create([
                    'payment_method_id' => $validated['payment_method_id'],
                    'amount' => $sale->total,
                    'received_amount' => $sale->total,
                    'change_amount' => 0,
                    'tip_amount' => 0,
                    'status' => 'completed',
                ]);

                $boxImpact = strtoupper((string) $paymentMethod->code) === 'CASH'
                    ? round((float) $sale->total, 2)
                    : 0.0;
                $movementTotal = (float) BoxMovement::query()
                    ->where('box_session_id', $session->id)
                    ->lockForUpdate()
                    ->sum('amount');
                $balanceBefore = round((float) $session->opening_balance + $movementTotal, 2);
                $balanceAfter = round($balanceBefore + $boxImpact, 2);

                $movement = $session->movements()->create([
                    'box_id' => $box->id,
                    'box_session_id' => $session->id,
                    'sale_id' => $sale->id,
                    'payment_id' => $payment->id,
                    'user_id' => Auth::id(),
                    'movement_type' => 'sale_income',
                    'amount' => $boxImpact,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => 'Venta POS #' . $sale->id . ' | Metodo ' . $paymentMethod->name,
                    'occurred_at' => now(),
                ]);

                BoxAuditLog::query()->create([
                    'box_id' => $box->id,
                    'box_session_id' => $session->id,
                    'user_id' => Auth::id(),
                    'action' => 'sale_income',
                    'description' => 'Ingreso automatico por venta POS #' . $sale->id,
                    'metadata' => [
                        'movement_id' => $movement->id,
                        'payment_method' => $paymentMethod->code,
                        'amount' => $boxImpact,
                    ],
                    'occurred_at' => now(),
                ]);
            }

            return response()->json($sale->load('items', 'payments'), 201);
        });
    }

    public function show(string $id)
    {
        $sale = Sale::with('items', 'payments')->findOrFail($id);
        return response()->json($sale);
    }
}

