<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Box;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoxController extends Controller
{
    public function index()
    {
        return response()->json(Box::all());
    }

    public function open(Request $request, $id)
    {
        $box = Box::findOrFail($id);
        $validated = $request->validate([
            'opening_balance' => 'required|numeric|min:0',
        ]);

        if (!$box->isOpen()) {
            $box->openBox($validated['opening_balance']);
            $box->user_id = Auth::id();
            $box->save();
            return response()->json($box);
        }

        return response()->json(['error' => 'La caja ya está abierta'], 422);
    }

    public function close(Request $request, $id)
    {
        $box = Box::findOrFail($id);
        $validated = $request->validate([
            'closing_balance' => 'required|numeric|min:0',
        ]);

        if ($box->isOpen()) {
            $box->closeBox($validated['closing_balance']);
            return response()->json($box);
        }

        return response()->json(['error' => 'La caja ya está cerrada'], 422);
    }
}
