<?php

namespace App\Http\Controllers;

use App\Services\WarehousePortalService;
use Illuminate\Http\Request;

class WarehousePortalController extends Controller
{
    public const TABS = ['pending', 'selected', 'answered'];

    public function index(Request $request, string $token, WarehousePortalService $portal)
    {
        $access = $portal->resolve($token);
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'pending';
        $answered = $tab === 'answered';
        $base = $portal->openItems($access->warehouse_id);
        $pendingCount = (clone $base)->doesntHave('offers')->count();
        $selectedCount = $portal->selections($access->warehouse_id)->whereIn('status', WarehousePortalService::CONFIRMABLE)->count();

        if ($tab === 'selected') {
            $items = $portal->selections($access->warehouse_id)
                ->with(['orderItem.product.brand', 'orderItem.product.genders', 'orderItem.product.type', 'orderItem.variant.size', 'logs'])
                ->orderByRaw("CASE WHEN status IN ('selected','notified') THEN 0 ELSE 1 END")->orderByDesc('id')
                ->paginate(20)->withQueryString();
        } else {
            $items = ($answered ? $base->has('offers') : $base->doesntHave('offers'))
                ->with(['orderItem.product.brand', 'orderItem.product.genders', 'orderItem.product.type', 'orderItem.variant.size', 'offers'])
                ->orderBy('id')->paginate(20)->withQueryString();
        }

        return response()->view('warehouse.portal', compact('access', 'token', 'tab', 'answered', 'pendingCount', 'selectedCount', 'items', 'portal'));
    }

    public function confirm(Request $request, string $token, int $allocation, WarehousePortalService $portal)
    {
        $portal->resolve($token);
        $data = $request->validate([
            'action' => ['required', 'in:reserve,problem'],
            'problem_type' => ['nullable', 'required_if:action,problem', 'in:'.implode(',', array_keys(WarehousePortalService::PORTAL_PROBLEMS))],
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['problem_type.required_if' => 'Problemin növünü seçin.']);
        $portal->confirm($token, $allocation, $data['action'], $data);

        return redirect()->route('warehouse.portal', ['token' => $token, 'tab' => 'selected'])
            ->with('success', $data['action'] === 'reserve' ? 'Rezerv təsdiqləndi. Təşəkkür edirik!' : 'Problem operatora bildirildi.');
    }

    public function answer(Request $request, string $token, int $item, WarehousePortalService $portal)
    {
        $portal->resolve($token);
        $data = $request->validate([
            'availability' => ['required', 'in:available,partial,unavailable'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:1000'],
            'replaces' => ['nullable', 'integer'], // düzəliş: düzəldilən cavabın id-si
        ]);
        $portal->answer($token, $item, $data);

        return redirect()->route('warehouse.portal', array_filter(['token' => $token, 'tab' => $request->filled('replaces') ? 'answered' : null]))
            ->with('success', $request->filled('replaces') ? 'Cavabınız yeniləndi.' : 'Cavabınız qeydə alındı.');
    }
}
