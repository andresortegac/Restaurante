<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function getMethods()
    {
        return response()->json(PaymentMethod::where('active', true)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'reference' => 'nullable|string',
        ]);

        $sale = Sale::findOrFail($validated['sale_id']);

        if ($validated['amount'] > $sale->total) {
            return response()->json(['error' => 'El monto excede el total de la venta'], 422);
        }

        $payment = Payment::create($validated);
        return response()->json($payment, 201);
    }
}
