<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Statistika: seçilmiş dövr (bu gün / bu həftə / bu ay) üzrə ətraflı analitika —
 * dövriyyə, mənfəət, satış qrafiki, ən çox satılanlar, ödəniş üsulları, mənbələr, axtarış, maliyyə.
 * Əməliyyat göstəriciləri (diqqət tələb edənlər, aktiv sifarişlər) əsas səhifədədir.
 */
class StatisticsController extends Controller
{
    /** Ən çox satılanlar — sabit say, dövr uzandıqca siyahı böyümür */
    public const TOP_LIMIT = 10;

    public function index(Request $request, AdminDashboard $stats): View
    {
        $period = AdminDashboard::period($request->query('period'));
        $days = $period === 'month' ? 30 : 7;
        // Dizayn yoxlaması: lokal mühitdə ?demo=1 — saxta rəqəmlər, bazaya toxunmur
        $demo = app()->environment('local') && $request->boolean('demo');
        $canFinance = (bool) auth('admin')->user()?->can('finance');

        $data = $demo ? $stats->demo($period, $days) : [
            'stats' => $stats->stats($period),
            'payments' => $stats->paymentMethods($period),
            'sales' => $stats->sales($days),
            'top' => $stats->topProducts($period, self::TOP_LIMIT),
            'searches' => $stats->searches($period),
            'online' => $stats->onlinePayments($period),
            'sources' => $stats->sources($period),
            'customerSources' => $stats->customerSources($period),
            'finance' => $canFinance ? $stats->finance() : null,
        ];
        if (!$canFinance) {
            $data['finance'] = null;
        }

        return view('backend.statistics.index', ['period' => $period, 'days' => $days, 'demo' => $demo] + $data);
    }
}
