<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Services\FinanceService;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Kuryer tətbiqi (/kuryer): ana səhifə (aktiv sifarişlər) və hesab (haqq-hesab tarixçəsi).
 * Sifarişin səhifəsi və əməliyyatlar — Backend\CourierController, baş kuryer — Backend\CourierTeamController.
 */
class CourierAppController extends Controller
{
    public function home(FinanceService $finance, OrderStatusService $statuses): View
    {
        $user = auth('admin')->user();
        $orders = Order::with(['status', 'address', 'customer', 'items.allocations', 'items.product'])
            ->where('courier_id', $user->id)
            ->where(fn ($q) => $q->whereHas('status', fn ($s) => $s->whereIn('code', ['courier_assigned', 'sent']))
                // təhvil verilib, amma qapıda imtina edilən məhsul hələ depoya qaytarılmayıb
                ->orWhereHas('items.allocations', fn ($a) => $a->where('status', OrderItemAllocation::RETURNING)))
            ->orderBy('id')->get();

        return view('courier-app.home', [
            'orders' => $orders,
            'activeCount' => $orders->filter(fn ($o) => in_array($o->status?->code, ['courier_assigned', 'sent'], true))->count(),
            'balance' => $finance->balances()[$finance->courierAccount($user)->id] ?? 0,
            'deliveredToday' => Order::where('courier_id', $user->id)
                ->whereHas('status', fn ($q) => $q->where('code', 'delivered'))->whereDate('updated_at', today())->count(),
            'statuses' => $statuses,
        ]);
    }

    public function account(FinanceService $finance): View
    {
        $user = auth('admin')->user();
        $account = $finance->courierAccount($user);

        return view('courier-app.account', [
            'user' => $user,
            'balance' => $finance->balances()[$account->id] ?? 0,
            'history' => $finance->courierHistory($account),
        ]);
    }

    /** Kuryerin son yeri (tətbiq açılanda / qayıdanda — app.js); admin Kuryerlər səhifəsində görünür */
    public function location(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);
        auth('admin')->user()->forceFill([
            'location_lat' => round((float) $data['lat'], 7),
            'location_lng' => round((float) $data['lng'], 7),
            'location_accuracy' => isset($data['accuracy']) ? (int) min(round((float) $data['accuracy']), 4294967295) : null,
            'location_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** public/courier-app/sw.js — əhatəsi /kuryer (ana səhifə /kuryer sonda "/" olmadan açılır, ona görə Service-Worker-Allowed) */
    public function serviceWorker(): Response
    {
        return response((string) file_get_contents(public_path('courier-app/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/kuryer',
        ]);
    }
}
