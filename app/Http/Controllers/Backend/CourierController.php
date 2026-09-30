<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Services\FinanceService;
use App\Services\OrderStatusService;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kuryerin iş ekranı: ona təyin olunmuş sifariş — anbarlardan götürmə, anbara ödəniş, çatdırılma.
 * Yalnız "Kuryer" rolu və yalnız öz sifarişləri (orders.courier_id).
 */
class CourierController extends Controller
{
    public function order(Order $order, FinanceService $finance, OrderStatusService $statuses): View
    {
        $this->authorizeCourier($order);
        $order->load(['status', 'paymentMethod', 'customer', 'address',
            'items.product.brand', 'items.variant.size', 'items.allocations.warehouse', 'items.allocations.logs']);

        $parts = $order->items->flatMap(fn ($item) => $item->allocations
            ->where('status', '!=', OrderItemAllocation::CANCELLED)->map(fn ($a) => ['item' => $item, 'a' => $a]));

        return view('backend.courier.order', [
            'order' => $order,
            'byWarehouse' => $parts->groupBy(fn ($row) => $row['a']->warehouse_id),
            'paid' => $finance->allocationPaid($parts->pluck('a.id')->all()),
            'collect' => $statuses->collectAmount($order),
            'deliveryBlock' => $statuses->deliveryBlock($order),
        ]);
    }

    public function pick(Order $order, int $allocation, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeCourier($order);
        $procurement->transition($order, $allocation, OrderItemAllocation::PICKED, [], $this->me());

        return back()->with('success', 'Götürüldü.');
    }

    public function problem(Request $request, Order $order, int $allocation, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate([
            'problem_type' => ['required', 'in:'.implode(',', array_keys(OrderItemAllocation::PROBLEM_TYPES))],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['problem_type.required' => 'Problemin növünü seçin.']);
        $procurement->transition($order, $allocation, OrderItemAllocation::PROBLEM, $data, $this->me());

        return back()->with('success', 'Problem operatora bildirildi.');
    }

    /** "Ödədim": kuryer anbara öz qalığından ödəyir (Kuryer → Anbar) */
    public function pay(Request $request, Order $order, int $allocation, FinanceService $finance): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99']], [
            'amount.required' => 'Məbləği yazın.', 'amount.min' => 'Məbləğ düzgün deyil.',
        ]);
        $part = OrderItemAllocation::with('warehouse')->whereIn('order_item_id', $order->items()->select('id'))->findOrFail($allocation);
        $finance->record($finance->courierAccount(auth('admin')->user()), $finance->warehouseAccount($part->warehouse),
            (float) $data['amount'], 'warehouse_payment', ['order_item_allocation_id' => $part->id, 'note' => 'Kuryer ödədi'], $this->me());

        return back()->with('success', number_format((float) $data['amount'], 2).' AZN anbara ödəniş qeydə alındı.');
    }

    public function start(Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $statuses->startDelivery($order, $this->me());

        return back()->with('success', 'Yola çıxdınız. Uğurlu yol!');
    }

    public function arrive(Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $statuses->arrive($order, $this->me());

        return back()->with('success', '"Ünvandayam" qeyd olundu.');
    }

    public function deliver(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['collected' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99']]);
        $statuses->deliver($order, $this->me(), isset($data['collected']) ? (float) $data['collected'] : null);

        return redirect()->route('admin.dashboard')->with('success', 'Sifariş '.$order->order_no.' təhvil verildi.');
    }

    public function deliveryProblem(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Nə baş verdiyini yazın.']);
        $statuses->deliveryProblem($order, $this->me(), $data['note']);

        return back()->with('success', 'Problem operatora bildirildi.');
    }

    private function authorizeCourier(Order $order): void
    {
        $user = auth('admin')->user();
        abort_unless($user?->hasRole(FinanceService::COURIER_ROLE, 'admin') && (int) $order->courier_id === (int) $user->id, 403, 'Bu sifariş sizə təyin olunmayıb.');
    }

    private function me(): int
    {
        return (int) auth('admin')->id();
    }
}
