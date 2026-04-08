<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->where('active', true)
            ->where(function ($query) {
                $query->whereIn('product_type', ['simple', 'combo'])
                    ->orWhereNull('product_type');
            })
            ->orderBy('name')
            ->get()
            ->map(function (Product $product): array {
                return [
                    'id' => (int) $product->id,
                    'name' => $this->sanitizeString($product->name),
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'sku' => $this->sanitizeString((string) $product->sku),
                    'product_type' => $product->product_type ?: 'simple',
                ];
            })
            ->values();

        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'required|string',
            'sku' => 'required|unique:products',
        ]);

        $product = Product::create($validated);
        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->all());
        return response()->json($product);
    }

    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    private function sanitizeString(string $value): string
    {
        if ($value === '' || preg_match('//u', $value)) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    }
}
