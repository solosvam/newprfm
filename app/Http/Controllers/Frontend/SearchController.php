<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\ProductSearchClick;
use App\Models\Product\ProductSearchLog;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function suggestions(Request $request, ProductSearchService $search): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) ($data['q'] ?? ''));
        $result = $search->search($query);
        $normalizedQuery = ProductSearchNormalizer::normalize($query);

        if (mb_strlen($normalizedQuery, 'UTF-8') < 2) {
            return response()->json($result);
        }

        $visitorId = $request->cookie('parfumshop_search_visitor') ?: (string) Str::uuid();
        $log = ProductSearchLog::create([
            'query' => $query,
            'normalized_query' => $normalizedQuery,
            'visitor_id' => $visitorId,
            'user_id' => auth()->id(),
            'result_count' => count($result['results']),
            'matched_product_ids' => array_column($result['results'], 'id'),
            'searched_at' => now(),
        ]);

        $result['search_log_id'] = $log->id;
        $response = response()->json($result);

        if (!$request->hasCookie('parfumshop_search_visitor')) {
            $response->cookie(
                'parfumshop_search_visitor',
                $visitorId,
                60 * 24 * 365 * 2,
                null,
                null,
                app()->environment('production'),
                true,
                false,
                'Lax'
            );
        }

        return $response;
    }

    public function click(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search_log_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'result_rank' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $log = ProductSearchLog::findOrFail($data['search_log_id']);

        abort_unless(
            hash_equals($log->visitor_id, (string) $request->cookie('parfumshop_search_visitor')),
            403
        );

        abort_unless(in_array((int) $data['product_id'], $log->matched_product_ids ?? [], true), 422);

        ProductSearchClick::firstOrCreate(
            [
                'search_log_id' => $log->id,
                'product_id' => $data['product_id'],
            ],
            [
                'result_rank' => $data['result_rank'],
                'clicked_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }
}
