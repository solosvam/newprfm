<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminDashboard;
use Illuminate\Http\Request;

class MainController extends Controller
{
    /** Əsas səhifə: əməliyyat paneli (kuryerlər — kuryer tətbiqində) */
    public function index(Request $request, AdminDashboard $stats)
    {
        // Kuryerlər admin panelə yox, kuryer tətbiqinə (/kuryer) yönləndirilir — CouriersToCourierApp
        // Əməliyyat paneli: nəyə baxmaq lazımdır. Ətraflı analitika — Statistika səhifəsində (StatisticsController)
        // Dizayn yoxlaması: lokal mühitdə /admin?demo=1 — saxta rəqəmlər, bazaya toxunmur
        $demo = app()->environment('local') && $request->boolean('demo');
        $source = $demo ? $stats->demo('today') : [
            'attention' => $stats->attention(),
            'active' => $stats->activeStatuses(),
            'carts' => $stats->carts(),
            'stats' => $stats->stats('today'),
        ];
        $dashboard = [
            'demo' => $demo,
            'attention' => $source['attention'],
            'active' => $source['active'],
            'carts' => $source['carts'],
            'today' => $source['stats'],
            'recent' => $stats->recentOrders(),
        ];

        return view('backend.pages.index', compact('dashboard'));
    }


    public function shortcuts()
    {
        return view('backend.shortcuts');
    }
}
