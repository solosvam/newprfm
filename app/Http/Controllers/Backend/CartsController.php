<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Satış → Səbətdəki mallar: daxil olmuş müştərilərin bazadakı səbətləri.
 *  - view=customers (standart): müştəri üzrə — məhsullar, dəyər, nə vaxtdan gözləyir;
 *  - view=products: məhsul üzrə — neçə müştərinin səbətindədir.
 * stale=1 — 24 saatdır səbətinə toxunmayan müştərilər; sort=value — dəyərə görə.
 */
class CartsController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->query('view') === 'products' ? 'products' : 'customers';
        $stale = $request->boolean('stale');
        $sort = $request->query('sort') === 'value' ? 'value' : 'waiting';

        $items = fn () => DB::table('customer_cart_items')
            ->join('product_variants', 'product_variants.id', '=', 'customer_cart_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'product_variants.size_id');

        if ($view === 'products') {
            $rows = $items()
                ->groupBy('product_variants.id', 'products.id', 'products.name', 'brands.name', 'sizes.name_az', 'product_variants.price')
                ->selectRaw('products.id as product_id, products.name, brands.name as brand, sizes.name_az as size, product_variants.price,
                    SUM(customer_cart_items.quantity) as qty, COUNT(DISTINCT customer_cart_items.customer_id) as customers,
                    MIN(customer_cart_items.created_at) as since')
                ->orderByDesc('customers')->orderByDesc('qty')
                ->paginate(50)->withQueryString();

            return view('backend.carts.index', compact('view', 'stale', 'sort', 'rows'));
        }

        $customers = DB::table('customer_cart_items')
            ->join('product_variants', 'product_variants.id', '=', 'customer_cart_items.product_variant_id')
            ->join('customers', 'customers.id', '=', 'customer_cart_items.customer_id')
            ->groupBy('customers.id', 'customers.name', 'customers.surname', 'customers.mobile')
            ->selectRaw('customers.id, customers.name, customers.surname, customers.mobile,
                SUM(customer_cart_items.quantity) as qty,
                SUM(customer_cart_items.quantity * product_variants.price) as value,
                MIN(customer_cart_items.created_at) as since,
                MAX(customer_cart_items.updated_at) as touched')
            ->when($stale, fn ($q) => $q->havingRaw('MAX(customer_cart_items.updated_at) < ?', [now()->subDay()]))
            ->when($sort === 'value', fn ($q) => $q->orderByDesc('value'), fn ($q) => $q->orderBy('since'))
            ->paginate(30)->withQueryString();

        $lines = $items()
            ->whereIn('customer_cart_items.customer_id', collect($customers->items())->pluck('id'))
            ->select('customer_cart_items.customer_id', 'customer_cart_items.quantity', 'customer_cart_items.created_at',
                'products.id as product_id', 'products.name', 'brands.name as brand', 'sizes.name_az as size', 'product_variants.price')
            ->orderBy('customer_cart_items.created_at')
            ->get()->groupBy('customer_id');

        return view('backend.carts.index', ['view' => $view, 'stale' => $stale, 'sort' => $sort, 'rows' => $customers, 'lines' => $lines]);
    }
}
