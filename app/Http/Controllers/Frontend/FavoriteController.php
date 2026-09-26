<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Support\LocalizedValidation;
use App\Models\Product\Product;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function guest()
    {
        return view('frontend.wishlist-guest');
    }

    public function index(Request $request)
    {
        $products = $request->user()->favoriteProducts()
            ->with(['brand', 'type', 'images', 'genders', 'variants' => fn ($q) => $q->where('active', 1)->orderBy('price'), 'variants.size'])
            ->where('products.active', 1)
            ->latest('product_favorites.created_at')
            ->get();

        return view('frontend.wishlist', compact('products'));
    }

    public function ids(Request $request)
    {
        return response()->json(['ids' => $request->user()->favoriteProducts()->pluck('products.id')->values()]);
    }

    public function store(Request $request, Product $product)
    {
        $request->user()->favoriteProducts()->syncWithoutDetaching([$product->id]);
        return response()->json(['status' => 'added', 'product_id' => $product->id]);
    }

    public function destroy(Request $request, Product $product)
    {
        $request->user()->favoriteProducts()->detach($product->id);
        return response()->json(['status' => 'removed', 'product_id' => $product->id]);
    }

    public function sync(Request $request)
    {
        $data = $request->validate(
            ['product_ids' => ['array', 'max:500'], 'product_ids.*' => ['integer', 'exists:products,id']],
            LocalizedValidation::messages(),
            LocalizedValidation::attributes()
        );
        $ids = collect($data['product_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids) $request->user()->favoriteProducts()->syncWithoutDetaching($ids);

        return response()->json(['status' => 'synced', 'ids' => $request->user()->favoriteProducts()->pluck('products.id')->values()]);
    }
}
