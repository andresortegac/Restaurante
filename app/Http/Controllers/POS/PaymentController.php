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
        $methods = PaymentMethod::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function (PaymentMethod $method): array {
                return [
                    'id' => (int) $method->id,
                    'name' => $this->sanitizeString($method->name),
                    'code' => $this->sanitizeNullableString($method->code),
                ];
            })
            ->values();

        return response()->json($methods);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0',
            'received_amount' => 'nullable|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string',
        ]);

        $sale = Sale::findOrFail($validated['sale_id']);

        if ($validated['amount'] > $sale->total) {
            return response()->json(['error' => 'El monto excede el total de la venta'], 422);
        }

        $payment = Payment::create([
            'sale_id' => $validated['sale_id'],
            'payment_method_id' => $validated['payment_method_id'],
            'amount' => $validated['amount'],
            'received_amount' => $validated['received_amount'] ?? $validated['amount'],
            'change_amount' => $validated['change_amount'] ?? 0,
            'tip_amount' => $validated['tip_amount'] ?? 0,
            'reference' => $validated['reference'] ?? null,
            'status' => 'completed',
        ]);
        return response()->json($payment, 201);
    }

    private function sanitizeString(?string $value): string
    {
        $value ??= '';

        if ($value === '' || preg_match('//u', $value)) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    }

    private function sanitizeNullableString(?string $value): ?string
    {
        return $value === null ? null : $this->sanitizeString($value);
    }
}
