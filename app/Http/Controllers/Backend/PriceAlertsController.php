<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Product\PriceAlert;
use App\Models\Product\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Satış → Endirim gözləyənlər: "Qiymət enəndə xəbər ver" abunəlikləri (price_alerts).
 *  - view=products (standart): ölçü üzrə — neçə müştəri gözləyir, abunə qiymətləri, indiki qiymət; endirim tabına keçid;
 *  - view=customers: müştəri üzrə — kim hansı ətirlərin ucuzlaşmasını gözləyir.
 * notified=1 — artıq xəbər verilənlər (qiymət enib, push gedib); standart — hələ gözləyənlər.
 */
class PriceAlertsController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->query('view') === 'customers' ? 'customers' : 'products';
        $notified = $request->boolean('notified');
        $scope = fn ($query) => $notified ? $query->whereNotNull('price_alerts.notified_at') : $query->whereNull('price_alerts.notified_at');

        $counts = [
            'waiting' => PriceAlert::whereNull('notified_at')->count(),
            'notified' => PriceAlert::whereNotNull('notified_at')->count(),
        ];

        if ($view === 'products') {
            $rows = $scope(DB::table('price_alerts'))
                ->groupBy('price_alerts.product_variant_id')
                ->selectRaw('price_alerts.product_variant_id, COUNT(*) as customers, MIN(price_alerts.price) as min_price,
                    MAX(price_alerts.price) as max_price, MIN(price_alerts.created_at) as since')
                ->orderByDesc('customers')->orderBy('since')
                ->paginate(50)->withQueryString();

            $variants = ProductVariant::with(['product.brand', 'product.activeDiscount', 'product.images' => fn ($images) => $images->limit(1), 'size'])
                ->whereIn('id', collect($rows->items())->pluck('product_variant_id'))
                ->get()->keyBy('id');

            return view('backend.price-alerts.index', compact('view', 'notified', 'counts', 'rows', 'variants'));
        }

        $rows = $scope(DB::table('price_alerts'))
            ->join('customers', 'customers.id', '=', 'price_alerts.customer_id')
            ->groupBy('customers.id', 'customers.name', 'customers.surname', 'customers.mobile')
            ->selectRaw('customers.id, customers.name, customers.surname, customers.mobile, COUNT(*) as alerts, MAX(price_alerts.created_at) as last_at')
            ->orderByDesc('alerts')->orderByDesc('last_at')
            ->paginate(30)->withQueryString();

        $alerts = $scope(PriceAlert::query())
            ->with(['variant.product.brand', 'variant.product.activeDiscount', 'variant.size'])
            ->whereIn('customer_id', collect($rows->items())->pluck('id'))
            ->latest()
            ->get()->groupBy('customer_id');

        return view('backend.price-alerts.index', compact('view', 'notified', 'counts', 'rows', 'alerts'));
    }
}
