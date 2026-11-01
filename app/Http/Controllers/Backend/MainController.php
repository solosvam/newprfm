<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboard;
use Illuminate\Http\Request;

class MainController extends Controller
{
    /** Əsas səhifə: rola görə — kuryerə öz sifarişləri, qalanlarına ümumi panel */
    public function index(Request $request, AdminDashboard $stats)
    {
        $user = auth('admin')->user();
        $courier = null;
        $dashboard = null;
        if (\App\Services\FinanceService::isCourier($user)) {
            $finance = app(\App\Services\FinanceService::class);
            $account = $finance->courierAccount($user);
            $orders = \App\Models\Order\Order::with(['status', 'address', 'customer', 'items.allocations', 'items.product'])
                ->where('courier_id', $user->id)
                ->where(fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', ['courier_assigned', 'sent', 'at_address']))
                    // təhvil verilib, amma qapıda imtina edilən məhsul hələ anbara qaytarılmayıb
                    ->orWhereHas('items.allocations', fn ($a) => $a->where('status', \App\Models\Procurement\OrderItemAllocation::RETURNING)))
                ->orderBy('id')->get();
            $courier = [
                'orders' => $orders,
                'balance' => $finance->balances()[$account->id] ?? 0,
                'deliveredToday' => \App\Models\Order\Order::where('courier_id', $user->id)
                    ->whereHas('status', fn ($q) => $q->where('code', 'delivered'))->whereDate('updated_at', today())->count(),
                'statuses' => app(\App\Services\OrderStatusService::class),
                'history' => $finance->courierHistory($account),
            ];
        } else {
            // Əməliyyat paneli: nəyə baxmaq lazımdır. Ətraflı analitika — Statistika səhifəsində (StatisticsController)
            // Dizayn yoxlaması: lokal mühitdə /admin?demo=1 — saxta rəqəmlər, bazaya toxunmur
            $demo = app()->environment('local') && $request->boolean('demo');
            $source = $demo ? $stats->demo('today') : [
                'attention' => $stats->attention(),
                'active' => $stats->activeStatuses(),
                'carts' => $stats->carts(),
                'stats' => $stats->stats('today'),
            ];
            $dashboard = [
                'demo' => $demo,
                'attention' => $source['attention'],
                'active' => $source['active'],
                'carts' => $source['carts'],
                'today' => $source['stats'],
                'recent' => $stats->recentOrders(),
            ];
        }

        return view('backend.pages.index', compact('courier', 'dashboard'));
    }


    public function shortcuts()
    {
        return view('backend.shortcuts');
    }
}
