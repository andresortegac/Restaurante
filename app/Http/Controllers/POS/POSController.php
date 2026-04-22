<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;

class POSController extends Controller
{
    /**
     * Show the POS interface
     */
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
                    'tracks_stock' => (bool) $product->tracks_stock,
                    'sku' => $this->sanitizeNullableString($product->sku),
                    'product_type' => $product->product_type ?: 'simple',
                    'image_url' => $product->image_url,
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

        $boxes = Box::all();
        $activeBox = Box::where('status', 'open')->first();

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
        $sales = Sale::query()
            ->with(['user', 'box', 'invoice', 'payments.paymentMethod', 'tableOrder.table', 'customer'])
            ->withCount('items')
            ->latest()
            ->paginate(15);

        $sales->getCollection()->transform(function (Sale $sale) {
            $this->sanitizeSaleForDisplay($sale);

            return $sale;
        });

        $todaySales = Sale::query()
            ->whereDate('created_at', today())
            ->count();

        $todayRevenue = (float) Sale::query()
            ->whereDate('created_at', today())
            ->sum('total');

        $pendingInvoices = Sale::query()
            ->doesntHave('invoice')
            ->count();

        return view('pos.sales-history.index', [
            'sales' => $sales,
            'todaySales' => $todaySales,
            'todayRevenue' => $todayRevenue,
            'pendingInvoices' => $pendingInvoices,
        ]);
    }

    private function sanitizeSaleForDisplay(Sale $sale): void
    {
        if ($sale->relationLoaded('user') && $sale->user) {
            $sale->user->name = $this->sanitizeString($sale->user->name);
        }

        $sale->customer_name = $sale->customer_name === null ? null : $this->sanitizeString($sale->customer_name);

        if ($sale->relationLoaded('customer') && $sale->customer) {
            $sale->customer->name = $this->sanitizeString($sale->customer->name);
        }

        if ($sale->relationLoaded('box') && $sale->box) {
            $sale->box->name = $this->sanitizeString($sale->box->name);
        }

        if ($sale->relationLoaded('tableOrder') && $sale->tableOrder) {
            $sale->tableOrder->order_number = $this->sanitizeString($sale->tableOrder->order_number);

            if ($sale->tableOrder->relationLoaded('table') && $sale->tableOrder->table) {
                $sale->tableOrder->table->name = $this->sanitizeString($sale->tableOrder->table->name);
            }
        }

        if ($sale->relationLoaded('invoice') && $sale->invoice) {
            $sale->invoice->invoice_number = $this->sanitizeString($sale->invoice->invoice_number);
            $sale->invoice->status = $this->sanitizeString($sale->invoice->status);
        }

        if ($sale->relationLoaded('payments')) {
            foreach ($sale->payments as $payment) {
                if ($payment->relationLoaded('paymentMethod') && $payment->paymentMethod) {
                    $payment->paymentMethod->name = $this->sanitizeString($payment->paymentMethod->name);
                }
            }
        }
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
