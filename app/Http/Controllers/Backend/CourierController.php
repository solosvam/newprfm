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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Kuryer tətbiqi (/kuryer, routes/courier.php): ona təyin olunmuş sifariş — depolardan götürmə, depoya ödəniş, çatdırılma.
 * Yalnız "Kuryer" rolu və yalnız öz sifarişləri (orders.courier_id).
 */
class CourierController extends Controller
{
    public function order(Order $order, FinanceService $finance, OrderStatusService $statuses): View
    {
        $this->authorizeCourier($order);
        // Kuryer sifarişi açdı — həmin sifarişin bildirişləri oxunmuş sayılır
        rescue(fn () => \App\Models\Courier\CourierNotification::where('user_id', $this->me())->where('order_id', $order->id)
            ->whereNull('read_at')->update(['read_at' => now()]), null, false);
        $order->load(['status', 'paymentMethod', 'customer', 'address',
            'items.product.brand', 'items.product.images', 'items.product.type', 'items.product.genders', 'items.variant.size', 'items.allocations.warehouse', 'items.allocations.logs']);

        $rows = fn (array $statuses, bool $not = false) => $order->items->flatMap(fn ($item) => $item->allocations
            ->filter(fn ($a) => in_array($a->status, $statuses, true) !== $not)->map(fn ($a) => ['item' => $item, 'a' => $a]));
        $parts = $rows(OrderItemAllocation::SUPPLY_INACTIVE, true);            // toplama
        $returning = $rows([OrderItemAllocation::RETURNING]);                  // qapıda imtina → depoya aparılacaq

        return view('courier-app.order', [
            'order' => $order,
            'byWarehouse' => $parts->groupBy(fn ($row) => $row['a']->warehouse_id),
            'returning' => $returning->groupBy(fn ($row) => $row['a']->warehouse_id),
            'paid' => $finance->allocationPaid($parts->merge($returning)->pluck('a.id')->all()),
            'collect' => $statuses->collectAmount($order),
            'deliveryBlock' => $statuses->deliveryBlock($order),
            'transferBlock' => $statuses->transferBlock($order),
            'couriers' => $statuses->couriers()->reject(fn ($u) => $u->id === $this->me())->values(),
            'doorBlock' => app(OrderItemCancellationService::class)->doorBlockReason($order),
        ]);
    }

    /** "Götürdüm": kuryer tətbiqinin kamerası ilə çəkilən şəkil məcburidir (PickPhotoStorage), sonra status dəyişir */
    public function pick(Request $request, Order $order, int $allocation, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeCourier($order);
        $this->withPickPhoto($request, $allocation, fn () => $procurement->transition($order, $allocation, OrderItemAllocation::PICKED, [], $this->me()));

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

    /**
     * "Ödədim": kuryer depoya öz qalığından ödəyir (Kuryer → Depo). Məbləğ yazılmır — seçimin ödənilməmiş qalığı
     * (say × alış qiyməti − artıq ödənilən) ödənilir. $pick — "… ödədim, götürdüm": ödəniş və götürmə birlikdə.
     */
    public function pay(Request $request, Order $order, int $allocation, FinanceService $finance, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeCourier($order);
        $part = OrderItemAllocation::with('warehouse')->whereIn('order_item_id', $order->items()->select('id'))->findOrFail($allocation);
        abort_unless($part->courier_pays, 403, 'Bu depoya pulu şirkət ödəyir — yalnız "Götürdüm" seçin.');
        $left = (int) round($part->quantity * (float) $part->unit_cost * 100) - ($finance->allocationPaid([$part->id])[$part->id] ?? 0);
        $pick = $request->boolean('pick') && $part->status !== OrderItemAllocation::PICKED;

        $pay = fn () => \Illuminate\Support\Facades\DB::transaction(function () use ($finance, $procurement, $order, $part, $left, $pick) {
            if ($left > 0) {
                $finance->record($finance->courierAccount(auth('admin')->user()), $finance->warehouseAccount($part->warehouse),
                    $left / 100, 'warehouse_payment', ['order_item_allocation_id' => $part->id, 'note' => 'Kuryer ödədi'], $this->me());
            }
            if ($pick) {
                $procurement->transition($order, $part->id, OrderItemAllocation::PICKED, [], $this->me());
            }
        });
        $pick ? $this->withPickPhoto($request, $part->id, $pay) : $pay(); // götürmə — şəkillə
        $paid = $left > 0 ? number_format($left / 100, 2).' AZN depoya ödəndi' : 'Depoya artıq ödənilib';

        return back()->with('success', $paid.($request->boolean('pick') ? ', götürüldü.' : '.'));
    }

    /** Ünvan "əlaqə ilə" olan sifariş: kuryer müştəri ilə danışandan sonra görüş yerini yazır */
    public function meetingPlace(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['meeting_place' => ['required', 'string', 'max:500']], ['meeting_place.required' => 'Görüş yerini yazın.']);
        $order->update(['meeting_place' => trim($data['meeting_place'])]);

        return back()->with('success', 'Görüş yeri yadda saxlandı.');
    }

    public function start(Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $statuses->startDelivery($order, $this->me());

        return back()->with('success', 'Yola çıxdınız. Uğurlu yol!');
    }

    public function deliver(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['collected' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99']]);
        $statuses->deliver($order, $this->me(), isset($data['collected']) ? (float) $data['collected'] : null);

        return redirect()->route('kuryer.home')->with('success', 'Sifariş '.$order->order_no.' təhvil verildi.');
    }

    public function deliveryProblem(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => 'Nə baş verdiyini yazın.']);
        $statuses->deliveryProblem($order, $this->me(), $data['note']);

        return back()->with('success', 'Problem operatora bildirildi.');
    }

    /** Qapıda imtina: müştəri məhsullardan birini götürmədi — məbləğ azalır, məhsul depoya qaytarılır */
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
            .number_format((float) $cancellation->amount, 2).' AZN. Məhsulu depoya qaytarın.');
    }

    /** "Qaytardım": qapıda imtina edilən məhsul depoya təhvil verildi */
    public function returned(Request $request, Order $order, int $allocation, ProcurementService $procurement, FinanceService $finance): RedirectResponse
    {
        $this->authorizeCourier($order);
        $refunded = $request->boolean('refunded');

        // Mal depoya qaytarıldı; depoya pul ödənilmişdisə və depo pulu kuryerə qaytardısa — kuryerin hesabına geri yazılır
        [$part, $amount] = DB::transaction(function () use ($order, $allocation, $procurement, $finance, $refunded) {
            $part = $procurement->markReturned($order, $allocation, $this->me());
            $paid = $finance->allocationPaid([$part->id])[$part->id] ?? 0;
            if (!$refunded || $paid <= 0 || !$part->courier_pays) { // pulu şirkət ödəyibsə depo şirkətə qaytarır
                return [$part, 0];
            }
            $finance->record($finance->warehouseAccount($part->warehouse), $finance->courierAccount(auth('admin')->user()), $paid / 100, 'warehouse_refund', [
                'order_item_allocation_id' => $part->id, 'note' => 'Mal depoya qaytarıldı, depo pulu kuryerə verdi',
            ], $this->me());

            return [$part, $paid];
        });

        return back()->with('success', 'Depoya qaytarıldı: '.($part->warehouse?->name_az ?? 'depo').'.'
            .($amount > 0 ? ' Depodan geri aldığınız '.number_format($amount / 100, 2).' AZN hesabınıza yazıldı.' : ''));
    }

    /** Sifarişi başqa kuryerə ötür (toplamadan əvvəl) */
    public function transfer(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorizeCourier($order);
        $data = $request->validate(['courier_id' => ['required', 'integer']], ['courier_id.required' => 'Kuryeri seçin.']);
        $to = $statuses->couriers()->firstWhere('id', (int) $data['courier_id']);
        abort_if(!$to, 422, 'Kuryer tapılmadı.');
        $statuses->transferCourier($order, $to, $this->me());

        return redirect()->route('kuryer.home')->with('success', 'Sifariş '.$order->order_no.' '.trim($to->full_name).' adlı kuryerə ötürüldü.');
    }

    /**
     * Götürmə şəkli: yoxlanılır və saxlanılır, sonra əməliyyat; əməliyyat alınmasa fayl silinir.
     * Şəkil təminat hissəsinə yazılır (admin sifarişin təminatında görür).
     */
    private function withPickPhoto(Request $request, int $allocationId, \Closure $action): void
    {
        $request->validate(
            ['photo' => ['required', 'file', 'image', 'max:10240']],
            ['photo.required' => 'Götürdüyünüz məhsulun şəklini çəkin.', 'photo.image' => 'Şəkil oxunmadı — yenidən çəkin.', 'photo.max' => 'Şəkil çox böyükdür.'],
        );
        $name = \App\Services\Courier\PickPhotoStorage::store($request->file('photo'));
        try {
            $action();
        } catch (\Throwable $e) {
            \App\Services\Courier\PickPhotoStorage::delete($name);
            throw $e;
        }
        $old = OrderItemAllocation::whereKey($allocationId)->value('pick_photo');
        OrderItemAllocation::whereKey($allocationId)->update(['pick_photo' => $name]);
        if ($old && $old !== $name) {
            \App\Services\Courier\PickPhotoStorage::delete($old);
        }
    }

    private function authorizeCourier(Order $order): void
    {
        $user = auth('admin')->user();
        abort_unless(FinanceService::isCourier($user) && (int) $order->courier_id === (int) $user->id, 403, 'Bu sifariş sizə təyin olunmayıb.');
    }

    private function me(): int
    {
        return (int) auth('admin')->id();
    }
}
