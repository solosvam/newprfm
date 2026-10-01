<?php

namespace App\Services\Search;

use App\Models\Product\ProductSearchLog;

/**
 * Axtarış jurnalı. Axtarış qutusu yazdıqca sorğu göndərir ("fere" → "ferre" → "ferreg" → "ferregam") —
 * jurnalda yalnız son yazılış qalsın deyə eyni ziyarətçinin son 2 dəqiqədəki "davam" qeydləri silinir.
 * Beləcə "Nəticəsiz axtarışlar" yazma prosesinin izləri ilə yox, real səhv yazılışlarla dolur.
 */
class ProductSearchLogger
{
    private const TYPING_WINDOW_SECONDS = 120;

    public function record(string $query, string $normalizedQuery, string $visitorId, ?int $userId, array $productIds): ProductSearchLog
    {
        $this->forgetTyping($visitorId, $normalizedQuery);

        return ProductSearchLog::create([
            'query' => mb_substr($query, 0, 100),
            'normalized_query' => mb_substr($normalizedQuery, 0, 100),
            'visitor_id' => $visitorId,
            'user_id' => $userId,
            'result_count' => count($productIds),
            'matched_product_ids' => array_values($productIds),
            'searched_at' => now(),
        ]);
    }

    /**
     * Yenisi əvvəlkinin davamıdır ("ferre" → "ferreg") və ya əvvəlkinin silinmiş halıdır ("ferregam" → "ferrega", backspace) —
     * əvvəlki qeyd silinir. Klik olunmuş qeydlərə toxunulmur (klik statistikası itməsin).
     */
    private function forgetTyping(string $visitorId, string $normalizedQuery): void
    {
        $recent = ProductSearchLog::query()
            ->where('visitor_id', $visitorId)
            ->where('searched_at', '>=', now()->subSeconds(self::TYPING_WINDOW_SECONDS))
            ->doesntHave('clicks')
            ->get(['id', 'normalized_query']);

        $ids = $recent
            ->filter(fn (ProductSearchLog $log) => $log->normalized_query !== ''
                && (str_starts_with($normalizedQuery, $log->normalized_query) || str_starts_with($log->normalized_query, $normalizedQuery)))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            ProductSearchLog::query()->whereIn('id', $ids)->delete();
        }
    }
}
