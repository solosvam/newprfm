<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\OrderItemCancellationService;
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

        $rows = fn (array $statuses, bool $not = false) => $order->items->flatMap(fn ($item) => $item->allocations
            ->filter(fn ($a) => in_array($a->status, $statuses, true) !== $not)->map(fn ($a) => ['item' => $item, 'a' => $a]));
        $parts = $rows(OrderItemAllocation::SUPPLY_INACTIVE, true);            // toplama
        $returning = $rows([OrderItemAllocation::RETURNING]);                  // qapıda imtina → anbara aparılacaq

        return view('backend.courier.order', [
            'order' => $order,
            'byWarehouse' => $parts->groupBy(fn ($row) => $row['a']->warehouse_id),
            'returning' => $returning->groupBy(fn ($row) => $row['a']->warehouse_id),
            'paid' => $finance->allocationPaid($parts->pluck('a.id')->all()),
            'collect' => $statuses->collectAmount($order),
            'deliveryBlock' => $statuses->deliveryBlock($order),
            'transferBlock' => $statuses->transferBlock($order),
            'couriers' => $statuses->couriers()->reject(fn ($u) => $u->id === $this->me())->values(),
            'doorBlock' => app(OrderItemCancellationService::class)->doorBlockReason($order),
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

    /** Qapıda imtina: müştəri məhsullardan birini götürmədi — məbləğ azalır, məhsul anbara qaytarılır */
    public function refuse(Request $request, Order $order, OrderItem $item, OrderItemCancellationService $service): RedirectResponse
    {
        $this->authorizeCourier($order);
        abort_unless($item->order_id === $order->id, 404);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['quantity.required' => 'Sayı seçin.']);
        $cancellation = $service->refuseAtDoor($order, $item, (int) $data['quantity'], $data['note'] ?? null, $this->me());

        return back()->with('success', 'Qapıda imtina qeyd olundu: '.$cancellation->quantity.' ədəd, −'
            .number_format((float) $cancellation->amount, 2).' AZN. Məhsulu anbara qaytarın.');
    }

    /** "Qaytardım": qapıda imtina edilən məhsul anbara təhvil verildi */
    public function returned(Order $order, int $allocation, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeCourier($order);
        $part = $procurement->markReturned($order, $allocation, $this->me());

        return back()->with('success', 'Anbara qaytarıldı: '.($part->warehouse?->name_az ?? 'anbar').'.');
    }

    /** Sifarişi başqa kuryerə ötür (toplamadan əvvəl) */
    public function transfer(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['courier_id' => ['required', 'integer']], ['courier_id.required' => 'Kuryeri seçin.']);
        $to = $statuses->couriers()->firstWhere('id', (int) $data['courier_id']);
        abort_if(!$to, 422, 'Kuryer tapılmadı.');
        $statuses->transferCourier($order, $to, $this->me());

        return redirect()->route('admin.dashboard')->with('success', 'Sifariş '.$order->order_no.' '.trim($to->full_name).' adlı kuryerə ötürüldü.');
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
