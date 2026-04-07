<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Http\Request;

class POSController extends Controller
{
    /**
     * Show the POS interface
     */
    public function index()
    {
        $products = Product::where('active', true)->get();
        $paymentMethods = PaymentMethod::where('active', true)->get();
        $boxes = Box::all();
        $activeBox = Box::where('status', 'open')->first();

        return view('pos.index', [
            'products' => $products,
            'paymentMethods' => $paymentMethods,
            'boxes' => $boxes,
            'activeBox' => $activeBox,
        ]);
    }
}
