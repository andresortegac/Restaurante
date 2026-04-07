<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function show($id)
    {
        $invoice = Invoice::findOrFail($id);
        return response()->json($invoice->load('sale'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'invoice_type' => 'required|in:factura,boleta',
        ]);

        $sale = Sale::findOrFail($validated['sale_id']);

        if ($sale->invoice) {
            return response()->json(['error' => 'Esta venta ya tiene una factura'], 422);
        }

        $invoice = new Invoice($validated);
        $invoice->invoice_number = $invoice->generateInvoiceNumber();
        $invoice->status = 'issued';
        $invoice->issued_at = now();
        $invoice->save();

        return response()->json($invoice, 201);
    }
}
