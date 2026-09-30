<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MainController extends Controller
{
    /** Əsas səhifə: rola görə — kuryerə öz sifarişləri, qalanlarına ümumi panel */
    public function index()
    {
        $user = auth('admin')->user();
        $courier = null;
        if ($user?->hasRole(\App\Services\FinanceService::COURIER_ROLE, 'admin')) {
            $finance = app(\App\Services\FinanceService::class);
            $account = $finance->courierAccount($user);
            $orders = \App\Models\Order\Order::with(['status', 'address', 'customer', 'items.allocations', 'items.product'])
                ->where('courier_id', $user->id)
                ->whereHas('status', fn ($q) => $q->whereIn('code', ['courier_assigned', 'sent', 'at_address']))
                ->orderBy('id')->get();
            $courier = [
                'orders' => $orders,
                'balance' => $finance->balances()[$account->id] ?? 0,
                'deliveredToday' => \App\Models\Order\Order::where('courier_id', $user->id)
                    ->whereHas('status', fn ($q) => $q->where('code', 'delivered'))->whereDate('updated_at', today())->count(),
                'statuses' => app(\App\Services\OrderStatusService::class),
                'history' => $this->courierHistory($account),
            ];
        }

        return view('backend.pages.index', compact('courier'));
    }

    /**
     * Kuryerin haqq-hesab tarixçəsi: hər hərəkət kuryer baxımından (+ ona gələn, − ondan çıxan)
     * və ondan sonrakı qalıq. Son 50 qeyd, yenisi yuxarıda.
     */
    private function courierHistory(\App\Models\Finance\FinanceAccount $account)
    {
        $running = 0;
        return \App\Models\Finance\MoneyMovement::with(['from', 'to', 'order'])
            ->where(fn ($q) => $q->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))
            ->orderBy('occurred_at')->orderBy('id')->get()
            ->map(function ($m) use ($account, &$running) {
                $cents = (int) round((float) $m->amount * 100);
                $delta = $m->to_account_id === $account->id ? $cents : -$cents;
                $running += $delta;
                $other = $m->to_account_id === $account->id ? $m->from : $m->to;
                return ['m' => $m, 'delta' => $delta, 'after' => $running, 'other' => $other];
            })
            ->reverse()->take(50)->values();
    }

    public function shortcuts()
    {
        return view('backend.shortcuts');
    }
}
