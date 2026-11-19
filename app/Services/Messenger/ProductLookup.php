<?php

namespace App\Services\Messenger;

use App\Models\Product\Product;
use App\Services\Search\ProductSearchService;

/**
 * Səsli köməkçinin "ətir axtar" funksiyası: saytın axtarışı (ProductSearchService — lüğət, səhv yazılışlar) ilə tapır,
 * aktiv ölçüləri və bu günkü qiymətləri (endirimlə) qaytarır. Stok deyilmir.
 */
class ProductLookup
{
    public const TOOL = 'etir_axtar';

    public const TOOL_DESCRIPTION = 'ParfumShop.az saytında ətiri adına və ya brendinə görə axtarır; ölçüləri və bu günkü qiymətləri (manatla) qaytarır. Qiymət soruşulanda və ya konkret ətirdən danışanda çağır.';

    public const TOOL_PARAMETERS = [
        'type' => 'object',
        'properties' => ['query' => ['type' => 'string', 'description' => 'Brend və/və ya ətirin adı latınca, məs. "Creed Aventus", "Dior Sauvage"']],
        'required' => ['query'],
        'additionalProperties' => false,
    ];

    public function __construct(private ProductSearchService $search)
    {
    }

    /**
     * @return list<array{id: int, name: string, url: string, sizes: list<array{size: string, price: float, old_price: ?float}>}>
     */
    public function find(string $query, int $limit = 3): array
    {
        $ids = array_column($this->search->search($query, $limit)['results'] ?? [], 'id');
        if ($ids === []) {
            return [];
        }
        $products = Product::query()->whereIn('id', $ids)->with([
            'brand', 'type', 'activeDiscount',
            'variants' => fn ($variants) => $variants->where('active', 1)->orderBy('price'),
            'variants.size',
        ])->get()->sortBy(fn (Product $p) => array_search($p->id, $ids))->values();

        return $products->map(function (Product $product) {
            $discounted = $product->visibleDiscount() !== null;

            return [
                'id' => $product->id,
                'name' => trim(implode(' ', array_filter([$product->brand?->name, $product->name, $product->type?->name_az]))),
                'url' => route('product', $product->slug),
                'sizes' => $product->variants->map(fn ($variant) => [
                    'size' => (string) ($variant->size?->name_az ?? ''),
                    'price' => round($variant->salePrice(), 2),
                    'old_price' => $discounted ? round((float) $variant->price, 2) : null,
                ])->values()->all(),
            ];
        })->all();
    }

    /** Modelə veriləcək qısa JSON (link modelə verilmir — səsdə oxunmasın, ayrıca mesajla gedir) */
    public static function forModel(array $products): string
    {
        if ($products === []) {
            return json_encode(['found' => false, 'note' => 'Saytda tapılmadı'], JSON_UNESCAPED_UNICODE);
        }

        // Qiymət hazır mətn kimi: model endirimi özü "təxmin etməsin" — endirim yalnız varsa yazılır
        $money = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.').' AZN';

        return json_encode(['found' => true, 'products' => array_map(fn ($p) => [
            'name' => $p['name'],
            'prices' => array_map(fn ($s) => $s['size'].': '.$money($s['price']).($s['old_price'] ? ' (endirimlə; əvvəl '.$money($s['old_price']).')' : ''), $p['sizes']),
        ], $products)], JSON_UNESCAPED_UNICODE);
    }
}
