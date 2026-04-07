<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\PromotionCoupon;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
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
            return response()->json(['error' => 'Cupón no válido'], 404);
        }

        $purchaseAmount = $validated['purchase_amount'] ?? 0;

        if (!$coupon->canBeUsed($purchaseAmount)) {
            return response()->json(['error' => 'Este cupón no puede ser utilizado'], 422);
        }

        return response()->json([
            'coupon' => $coupon,
            'discount' => $coupon->discount,
            'discount_amount' => $coupon->discount->calculateDiscountAmount($purchaseAmount),
        ]);
    }
}
