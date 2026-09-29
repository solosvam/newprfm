<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequest;
use App\Services\ProcurementService;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    public function warehouses()
    {
        return view('backend.procurement.warehouses', ['warehouses' => Warehouse::orderBy('name_az')->get()]);
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
            'address' => ['nullable', 'string', 'max:500'],
            'active' => ['required', 'boolean'],
        ]);

        return $data;
    }

    public function storeWarehouse(Request $request)
    {
        Warehouse::create($this->warehouseData($request));

        return back()->with('success', 'Anbar əlavə edildi.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse)
    {
        $warehouse->update($this->warehouseData($request));

        return back()->with('success', 'Anbar yeniləndi.');
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

    public function createRequests(Request $request, Order $order, ProcurementService $service)
    {
        $data = $request->validate([
            'warehouse_ids' => ['required', 'array', 'min:1', 'max:100'],
            'warehouse_ids.*' => ['required', 'integer', 'distinct'],
            'item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $service->createRequests($order, $data['warehouse_ids'], $data['item_ids'], (int) auth('admin')->id());

        return back()->with('success', 'Sorğular qeydə alındı. Anbarlarla əlaqə saxlayıb cavabları daxil edin.');
    }

    public function recordOffer(Request $request, Order $order, int $requestItem, ProcurementService $service)
    {
        $data = $request->validate([
            'available_quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'source' => ['required', 'in:phone,whatsapp,telegram,manual'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->recordOffer($order, $requestItem, $data, (int) auth('admin')->id());

        return back()->with('success', 'Anbarın cavabı qeydə alındı.');
    }

    public function allocate(Request $request, Order $order, ProcurementService $service)
    {
        $data = $request->validate([
            'offer_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);
        $service->allocate($order, (int) $data['offer_id'], (int) $data['quantity'], (int) auth('admin')->id(), $data['idempotency_key']);

        return back()->with('success', 'Anbar seçimi qeydə alındı. Bu qeyd rezervasiya təsdiqi deyil.');
    }

    public function cancelAllocation(Request $request, Order $order, int $allocation, ProcurementService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $service->cancelAllocation($order, $allocation, $data['note'], (int) auth('admin')->id());

        return back()->with('success', 'Anbar seçimi ləğv edildi. Məhsul sifarişdə qalır.');
    }
}
