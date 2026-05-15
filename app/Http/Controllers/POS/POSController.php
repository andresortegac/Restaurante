<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\BoxSession;
use App\Models\PaymentMethod;
use App\Models\Product;

class POSController extends Controller
{
    /**
     * Show the POS interface
     */
    public function index()
    {
        $products = Product::query()
            ->with('taxRate:id,rate,is_inclusive')
            ->visibleInMenu()
            ->orderedForMenu()
            ->get()
            ->map(function (Product $product): array {
                return [
                    'id' => (int) $product->id,
                    'name' => $this->sanitizeString($product->name),
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'tracks_stock' => (bool) $product->tracks_stock,
                    'sku' => $this->sanitizeNullableString($product->sku),
                    'product_type' => $product->product_type ?: 'simple',
                    'image_url' => $product->image_url,
                    'tax_rate' => (float) ($product->taxRate?->rate ?? 0),
                    'tax_inclusive' => (bool) ($product->taxRate?->is_inclusive ?? false),
                ];
            })
            ->values();

        $paymentMethods = PaymentMethod::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function (PaymentMethod $paymentMethod): array {
                return [
                    'id' => (int) $paymentMethod->id,
                    'name' => $this->sanitizeString($paymentMethod->name),
                    'code' => $this->sanitizeNullableString($paymentMethod->code),
                ];
            })
            ->values();

        $boxes = Box::query()->with('activeSession')->orderBy('name')->get();
        $activeSession = BoxSession::query()
            ->with('box')
            ->where('status', 'open')
            ->where('user_id', auth()->id())
            ->latest('opened_at')
            ->first();

        $activeBox = $activeSession?->box
            ?? Box::query()->where('status', 'open')->orderByDesc('opened_at')->first();

        return view('pos.index', [
            'products' => $products,
            'paymentMethods' => $paymentMethods,
            'boxes' => $boxes,
            'activeBox' => $activeBox,
            'initialProducts' => $products,
            'initialPaymentMethods' => $paymentMethods,
        ]);
    }

    public function salesHistory()
    {
        return redirect()->route('billing.history');
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
