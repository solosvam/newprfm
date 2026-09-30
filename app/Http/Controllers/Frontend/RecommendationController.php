<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Qonaq üçün "Tövsiyə olunanlar": bəyəndikləri brauzerdə saxlanır (localStorage),
 * main.js onları buraya göndərir → tərkibcə oxşar ətirlərin siyahısı (HTML <li>-lər).
 * Login olan müştəriyə server özü hesablayır (CatalogService::sidebarProducts).
 */
class RecommendationController extends Controller
{
    public function sidebar(Request $request, CatalogService $catalog, ProductRecommendationService $reco): Response
    {
        $raw = (string) $request->query('ids', '');
        abort_if(strlen($raw) > 600 || !preg_match('/^[0-9,]*$/', $raw), 422);
        $seeds = $reco->cleanIds(explode(',', $raw));
        if (!$seeds) {
            return response()->noContent();
        }
        $items = $catalog->recommendationsFor($seeds);

        return response()->view('frontend.includes.sidebar-products-items', ['items' => $items, 'ranked' => false])
            ->header('Cache-Control', 'private, max-age=300');
    }
}
