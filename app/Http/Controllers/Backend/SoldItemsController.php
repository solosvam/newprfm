<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentMethod;
use App\Models\Procurement\Warehouse;
use App\Services\Finance\SoldItemsReport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Satışlar → Satılan mallar (maliyyə icazəsi — maya və qazanc görünür) */
class SoldItemsController extends Controller
{
    public function index(Request $request, SoldItemsReport $report): View
    {
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'payment' => ['nullable', 'string', 'max:30'], 'warehouse' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100'],
        ]);
        $from = Carbon::parse($f['from'] ?? today())->startOfDay();
        $to = Carbon::parse($f['to'] ?? $from)->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $rows = $report->rows($from, $to, $f);
        $perPage = 100;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('backend.finance.sold-items', [
            'rows' => $paginator,
            'totals' => $report->totals($rows),
            'from' => $from, 'to' => $to, 'f' => $f,
            'presets' => [
                'Bu gün' => [today(), today()],
                'Dünən' => [today()->subDay(), today()->subDay()],
                'Bu həftə' => [today()->startOfWeek(), today()],
                'Bu ay' => [today()->startOfMonth(), today()],
            ],
            'paymentMethods' => PaymentMethod::orderBy('sort_order')->get(['code', 'name_az']),
            'warehouses' => Warehouse::orderBy('name_az')->get(['id', 'name_az']),
        ]);
    }
}
