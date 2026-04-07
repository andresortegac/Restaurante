<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\PromotionCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiscountController extends Controller
{
    public function create()
    {
        $coupons = PromotionCoupon::with('discount')
            ->latest()
            ->take(10)
            ->get();

        return view('pos.promotional-codes.create', [
            'coupons' => $coupons,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'discount_name' => 'required|string|max:255',
            'discount_description' => 'nullable|string|max:1000',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date|after_or_equal:discount_starts_at',
            'code' => 'required|string|max:50|regex:/^[A-Za-z0-9_-]+$/|unique:promotion_coupons,code',
            'usage_limit' => 'nullable|integer|min:1',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
            'active' => 'required|boolean',
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return back()
                ->withErrors(['discount_value' => 'El porcentaje no puede ser mayor a 100.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated) {
            $discount = Discount::create([
                'name' => $validated['discount_name'],
                'description' => $validated['discount_description'] ?? null,
                'type' => $validated['discount_type'],
                'value' => $validated['discount_value'],
                'starts_at' => $validated['discount_starts_at'] ?? null,
                'ends_at' => $validated['discount_ends_at'] ?? null,
                'active' => (bool) $validated['active'],
            ]);

            PromotionCoupon::create([
                'discount_id' => $discount->id,
                'code' => Str::upper(trim($validated['code'])),
                'usage_limit' => $validated['usage_limit'] ?? null,
                'usage_count' => 0,
                'min_purchase_amount' => $validated['min_purchase_amount'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'active' => (bool) $validated['active'],
            ]);
        });

        return redirect()
            ->route('pos.promo-codes.create')
            ->with('success', 'Codigo promocional creado correctamente.');
    }

    public function index()
    {
        $discounts = Discount::where('active', true)->get();
        return response()->json($discounts);
    }

    public function validateCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'purchase_amount' => 'nullable|numeric|min:0',
        ]);

        $coupon = PromotionCoupon::where('code', $validated['code'])->first();

        if (!$coupon) {
            return response()->json(['error' => 'Cupon no valido'], 404);
        }

        $purchaseAmount = $validated['purchase_amount'] ?? 0;

        if (!$coupon->canBeUsed($purchaseAmount)) {
            return response()->json(['error' => 'Este cupon no puede ser utilizado'], 422);
        }

        return response()->json([
            'coupon' => $coupon,
            'discount' => $coupon->discount,
            'discount_amount' => $coupon->discount->calculateDiscountAmount($purchaseAmount),
        ]);
    }
}

