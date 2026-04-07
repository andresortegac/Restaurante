<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $sale = Sale::create([
                'user_id' => Auth::id(),
                'box_id' => $validated['box_id'],
                'status' => 'completed',
            ]);

            foreach ($validated['items'] as $item) {
                $sale->addItem($item['product_id'], $item['quantity'], $item['unit_price']);
            }

            $sale->calculateTotal();
            return response()->json($sale->load('items'), 201);
        });
    }

    public function show(string $id)
    {
        $sale = Sale::with('items', 'payments')->findOrFail($id);
        return response()->json($sale);
    }
}
