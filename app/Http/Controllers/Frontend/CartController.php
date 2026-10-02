<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function index(Request $request, CartService $cart)
    {
        return response()->json(['items' => $cart->items($request->user())])->header('Cache-Control', 'private, no-store');
    }

    public function merge(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'items' => ['required', 'array', 'max:200'],
            'items.*.variant_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        return response()->json(['items' => $cart->merge($request->user(), $data['items'], $data['token'])])
            ->header('Cache-Control', 'private, no-store');
    }

    public function change(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['add', 'decrease', 'remove'])],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ]);

        return response()->json(['items' => $cart->change($request->user(), $data['variant_id'], $data['action'], $data['quantity'] ?? 1)])
            ->header('Cache-Control', 'private, no-store');
    }
}
