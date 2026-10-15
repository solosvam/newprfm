<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequest;
use App\Models\Procurement\OrderItemAllocation;
use App\Services\ProcurementService;
use App\Services\WarehouseNotifier;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    public function warehouses()
    {
        return view('backend.procurement.warehouses', ['warehouses' => Warehouse::orderBy('name_az')->get()]);
    }

    public function createLink(Warehouse $warehouse, \App\Services\WarehousePortalService $portal)
    {
        $url = $portal->issue($warehouse, (int) auth('admin')->id());

        return back()->with('warehouse_link', ['name' => $warehouse->name_az, 'url' => $url])
            ->with('success', 'Link hazırdır. Etibarlılıq müddəti 7 gündür. Köhnə etibarlı linklər də bu anbarın bütün açıq sorğularını göstərir.');
    }

    public function revokeLinks(Warehouse $warehouse)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($warehouse) {
            Warehouse::whereKey($warehouse->id)->lockForUpdate()->firstOrFail();
            \App\Models\Procurement\WarehouseAccessLink::where('warehouse_id', $warehouse->id)
                ->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });

        return back()->with('success', 'Anbarın bütün əvvəlki giriş linkləri ləğv edildi.');
    }

    public function editWarehouse(Warehouse $warehouse)
    {
        return view('backend.procurement.warehouse-edit', compact('warehouse'));
    }

    private function warehouseData(Request $request): array
    {
        $data = $request->validate([
            'name_az' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            // Telegram çat id-si: rəqəm, qrupda mənfi (-1001234567890)
            'telegram_id' => ['nullable', 'required_if:request_channel,telegram', 'regex:/^-?\d{1,20}$/'],
            // Sorğu tipi Telegram-dırsa, Telegram ID məcburidir
            'request_channel' => ['nullable', 'in:'.implode(',', array_keys(Warehouse::CHANNELS))],
            'address' => ['nullable', 'string', 'max:500'],
            'active' => ['required', 'boolean'],
        ], [
            'telegram_id.regex' => 'Telegram ID yalnız rəqəmlərdən ibarət olmalıdır (qrupda əvvəlində "-" ola bilər).',
            'telegram_id.required_if' => 'Sorğu tipi Telegram olanda Telegram ID yazılmalıdır.',
        ]);

        $data['request_channel'] ??= 'sms';

        return $data;
    }

    public function storeWarehouse(Request $request)
    {
        Warehouse::create($this->warehouseData($request));

        return back()->with('success', 'Anbar əlavə edildi.');
    }

    /**
     * Anbarı silmək. Anbarla pul haqq-hesabı olubsa (Kassada ödəniş / geri qaytarma) silinmir — deaktiv edilir, hesablaşmaların tarixçəsi ona bağlıdır.
     * Sorğu, cavab, link, price list və ləğv edilmiş seçimlər alış-veriş sayılmır — anbarla birlikdə silinir.
     * Hələ qüvvədə olan seçim varsa (sifarişin təminatı bu anbardandır) silinmir — əvvəl seçim ləğv edilməlidir.
     */
    public function destroyWarehouse(Warehouse $warehouse)
    {
        $account = \App\Models\Finance\FinanceAccount::where('type', 'warehouse')->where('warehouse_id', $warehouse->id)->first();
        $paid = $account && \Illuminate\Support\Facades\DB::table('money_movements')
            ->where(fn ($q) => $q->where('from_account_id', $account->id)->orWhere('to_account_id', $account->id))->exists();

        if ($paid) {
            $warehouse->update(['active' => false]);

            return back()->with('warning', $warehouse->name_az.' ilə pul haqq-hesabı olduğu üçün silinə bilməz — deaktiv edildi.');
        }
        $live = OrderItemAllocation::where('warehouse_id', $warehouse->id)->where('status', '!=', OrderItemAllocation::CANCELLED)->count();
        if ($live) {
            return back()->withErrors(['warehouse' => $warehouse->name_az.' '.$live.' qüvvədə olan seçimdə var (sifarişin məhsulu bu anbardan gəlir). Əvvəl həmin seçimləri ləğv edin, sonra silin.']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($warehouse, $account) {
            $allocationIds = OrderItemAllocation::where('warehouse_id', $warehouse->id)->pluck('id'); // yalnız ləğv edilmişlər qalıb
            \Illuminate\Support\Facades\DB::table('allocation_status_logs')->whereIn('order_item_allocation_id', $allocationIds)->delete();
            OrderItemAllocation::whereIn('id', $allocationIds)->delete();
            $requestIds = WarehouseRequest::where('warehouse_id', $warehouse->id)->pluck('id');
            $itemIds = \App\Models\Procurement\WarehouseRequestItem::whereIn('warehouse_request_id', $requestIds)->pluck('id');
            \App\Models\Procurement\WarehouseOffer::whereIn('warehouse_request_item_id', $itemIds)->delete();
            \App\Models\Procurement\WarehouseRequestItem::whereIn('id', $itemIds)->delete();
            WarehouseRequest::whereIn('id', $requestIds)->delete();
            \Illuminate\Support\Facades\DB::table('warehouse_access_links')->where('warehouse_id', $warehouse->id)->delete();
            $account?->delete();
            $warehouse->delete(); // price listlər bazada cascade ilə silinir
        });

        return back()->with('success', $warehouse->name_az.' silindi.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($this->warehouseData($request));

        return redirect()->route('admin.procurement.warehouses')->with('success', 'Anbar yeniləndi.');
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.variant.size', 'items.allocations.warehouse', 'items.allocations.logs', 'status']);
        $requests = WarehouseRequest::where('order_id', $order->id)
            ->with(['warehouse', 'items.offers', 'items.orderItem.product', 'items.orderItem.variant.size'])->latest('id')->get();

        return view('backend.procurement.order', [
            'order' => $order, 'requests' => $requests,
            'warehouses' => Warehouse::where('active', true)->orderBy('name_az')->get(),
        ]);
    }

    public function createRequests(Request $request, Order $order, ProcurementService $service, WarehouseNotifier $notifier)
    {
        $data = $request->validate([
            'warehouse_ids' => ['required', 'array', 'min:1', 'max:100'],
            'warehouse_ids.*' => ['required', 'integer', 'distinct'],
            'item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $actor = (int) auth('admin')->id();
        $created = $service->createRequests($order, $data['warehouse_ids'], $data['item_ids'], $actor);
        // SMS — tranzaksiyadan sonra; alınmasa sorğu qalır, "SMS-i təkrar göndər" ilə yenidən
        $results = $created->mapWithKeys(fn ($req) => [$req->warehouse->name_az => $notifier->notifyRequest($req, $actor)]);

        return back()->with('success', trim('Sorğular qeydə alındı. '.WarehouseNotifier::summary($results)));
    }

    /** "Anbar seç": sorğu göndərmədən anbarın cavabı yazılır və anbar dərhal seçilir (ProcurementService::recordDirect); anbara seçim SMS-i gedir */
    public function recordDirect(Request $request, Order $order, ProcurementService $service, WarehouseNotifier $notifier)
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
        ], [
            'warehouse_id.required' => 'Anbarı seçin.',
            'unit_cost.required' => 'Alış qiymətini yazın.',
            'unit_cost.min' => 'Qiymət 0-dan böyük olmalıdır.',
        ]);
        $actor = (int) auth('admin')->id();
        $allocation = $service->recordDirect($order, (int) $data['item_id'], (int) $data['warehouse_id'], (string) $data['unit_cost'], $actor);
        $sms = WarehouseNotifier::summary([$allocation->warehouse->name_az => $notifier->notifySelected($order, $allocation, $actor)]);

        return back()->with('success', trim($allocation->warehouse->name_az.' seçildi (sorğusuz). '.$sms));
    }

    public function recordOffer(Request $request, Order $order, int $requestItem, ProcurementService $service)
    {
        $data = $request->validate([
            'available_quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            // WhatsApp və Telegram ləğv olunub: operator cavabı telefonla alır, anbar özü isə linkdən yazır
            'source' => ['required', 'in:phone,manual'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->recordOffer($order, $requestItem, $data, (int) auth('admin')->id());

        return back()->with('success', 'Anbarın cavabı qeydə alındı.');
    }

    public function allocate(Request $request, Order $order, ProcurementService $service, WarehouseNotifier $notifier)
    {
        $data = $request->validate([
            'offer_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);
        $actor = (int) auth('admin')->id();
        $allocation = $service->allocate($order, (int) $data['offer_id'], (int) $data['quantity'], $actor, $data['idempotency_key']);
        // Təkrar göndərilən forma (eyni açar) ikinci SMS göndərmir
        if ($allocation->wasRecentlyCreated) {
            $log = $notifier->notifySelected($order, $allocation, $actor);
            $sms = WarehouseNotifier::summary([$allocation->warehouse->name_az => $log]);
        }

        return back()->with('success', trim('Anbar seçimi qeydə alındı. '.($sms ?? '')));
    }

    /** Təminat hissəsinin mərhələsi: bildirildi / ayrıldı / götürüldü / problem / davam et */
    public function transitionAllocation(Request $request, Order $order, int $allocation, ProcurementService $service)
    {
        $data = $request->validate([
            'action' => ['required', 'in:notified,reserved,picked,problem,resume'],
            'problem_type' => ['nullable', 'required_if:action,problem', 'in:'.implode(',', array_keys(\App\Models\Procurement\OrderItemAllocation::PROBLEM_TYPES))],
            'unit_cost' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['problem_type.required_if' => 'Problemin növünü seçin.']);
        $result = $service->transition($order, $allocation, $data['action'], $data, (int) auth('admin')->id());

        return back()->with('success', $result->warehouse?->name_az.': '.$result->label().'.');
    }

    public function cancelAllocation(Request $request, Order $order, int $allocation, ProcurementService $service, WarehouseNotifier $notifier)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $actor = (int) auth('admin')->id();
        $from = $service->cancelAllocation($order, $allocation, $data['note'], $actor);
        // Anbar xəbərdar idisə (bildirildi/rezerv/problem) — ləğv barədə SMS
        if (in_array($from, [OrderItemAllocation::NOTIFIED, OrderItemAllocation::RESERVED, OrderItemAllocation::PROBLEM], true)) {
            $model = OrderItemAllocation::with('warehouse')->find($allocation);
            $sms = WarehouseNotifier::summary([$model->warehouse->name_az => $notifier->notifyCancelled($model, $actor)]);
        }

        return back()->with('success', trim('Anbar seçimi ləğv edildi. Məhsul sifarişdə qalır. '.($sms ?? '')));
    }

    /** "SMS göndər / təkrar göndər" — sorğu üzrə */
    public function requestSms(Order $order, int $warehouseRequest, WarehouseNotifier $notifier)
    {
        $this->ensureOpen($order, \App\Services\OrderStatusService::PROCUREMENT);
        $req = WarehouseRequest::with('warehouse')->where('order_id', $order->id)->findOrFail($warehouseRequest);
        $log = $notifier->notifyRequest($req, (int) auth('admin')->id());

        return $this->smsResponse($req->warehouse->name_az, $log);
    }

    /** "SMS göndər / təkrar göndər" — seçim üzrə (Seçilib → Anbara bildirildi) */
    public function allocationSms(Order $order, int $allocation, WarehouseNotifier $notifier)
    {
        $this->ensureOpen($order, \App\Services\OrderStatusService::SUPPLY_FLOW);
        $model = OrderItemAllocation::with('warehouse')->whereIn('order_item_id', $order->items()->select('id'))->findOrFail($allocation);
        abort_unless(in_array($model->status, [OrderItemAllocation::SELECTED, OrderItemAllocation::NOTIFIED], true), 422, 'Bu mərhələdə SMS göndərilmir.');
        $log = $notifier->notifySelected($order, $model, (int) auth('admin')->id());

        return $this->smsResponse($model->warehouse->name_az, $log);
    }

    private function ensureOpen(Order $order, array $statuses): void
    {
        $order->loadMissing('status');
        abort_unless($order->customer_id && in_array($order->status?->code, $statuses, true), 422, 'Bu mərhələdə anbara SMS göndərilmir.');
    }

    private function smsResponse(string $warehouse, ?\App\Models\SmsLog $log)
    {
        if ($log?->isSent()) {
            return back()->with('success', $warehouse.': SMS göndərildi.');
        }

        return back()->with('error', $warehouse.': '.($log ? $log->error : 'anbarın telefonu yazılmayıb.'));
    }
}
