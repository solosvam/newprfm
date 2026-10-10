<?php

namespace App\Services\PriceList;

use App\Models\PriceList\WarehousePriceItem;
use App\Models\PriceList\WarehousePriceList;
use App\Models\Procurement\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Excel faylını anbarın yeni price listi kimi yazır.
 * Xəritə ($mapping): sheet, first_row (1-dən), name_col, price_col, brand_mode (group | column | none), brand_col.
 *  - group: qiyməti olmayan sətir brend başlığıdır, altındakı sətirlər o brendə aiddir (1C çıxarışı);
 *  - column: brend ayrıca sütundadır; none: brend yoxdur (ad yalnız axtarışla tapılır, avtomatik bağlanmır).
 */
class PriceListImporter
{
    public function __construct(private PriceListReader $reader, private PriceListMatcher $matcher)
    {
    }

    /** Faylın sütunlarını təxmin edir: ən çox mətn olan sütun — ad, ən çox rəqəm olan — qiymət */
    public function guessMapping(array $rows): array
    {
        $text = $number = [];
        $blankPrice = 0;
        foreach (array_slice($rows, 0, 200) as $row) {
            foreach ($row as $col => $value) {
                if (is_numeric($value) && (float) $value > 0) {
                    $number[$col] = ($number[$col] ?? 0) + 1;
                } elseif (is_string($value) && mb_strlen(trim($value)) > 5) {
                    $text[$col] = ($text[$col] ?? 0) + 1;
                }
            }
        }
        arsort($text);
        arsort($number);
        $name = array_key_first($text) ?? 0;
        $price = array_key_first($number) ?? 1;
        foreach (array_slice($rows, 0, 200) as $row) {
            $blankPrice += trim((string) ($row[$name] ?? '')) !== '' && !$this->price($row[$price] ?? null) ? 1 : 0;
        }

        return ['sheet' => 0, 'first_row' => 1, 'name_col' => $name, 'price_col' => $price,
            'brand_mode' => $blankPrice > 3 ? 'group' : 'none', 'brand_col' => null];
    }

    public function import(Warehouse $warehouse, string $path, string $fileName, array $mapping, ?int $actor): WarehousePriceList
    {
        $rows = $this->reader->rows($path, (int) $mapping['sheet']);
        $memory = DB::table('warehouse_price_matches')->where('warehouse_id', $warehouse->id)->pluck('product_variant_id', 'name_key');
        $liveVariants = DB::table('product_variants')->pluck('id')->flip();

        $items = [];
        $brand = null;
        foreach ($rows as $index => $row) {
            if ($index + 1 < (int) $mapping['first_row']) {
                continue;
            }
            $name = trim((string) ($row[$mapping['name_col']] ?? ''));
            $price = $this->price($row[$mapping['price_col']] ?? null);
            if ($name === '') {
                continue;
            }
            if ($mapping['brand_mode'] === 'group' && $price === null) {
                $brand = $name; // brend başlığı
                continue;
            }
            if ($price === null) {
                continue; // qiymətsiz sətir (başlıq, qeyd)
            }
            if ($mapping['brand_mode'] === 'column') {
                $brand = trim((string) ($row[$mapping['brand_col']] ?? '')) ?: null;
            }

            $parsed = PriceListNameParser::parse($name, $brand);
            $key = PriceListNameParser::key($name);
            $brandId = $this->matcher->brandId($brand);
            $remembered = $memory[$key] ?? null;
            $variant = $remembered && $liveVariants->has($remembered) ? $remembered : null;
            $by = $variant ? 'memory' : null;
            if (!$variant && ($variant = $this->matcher->auto($brandId, $parsed))) {
                $by = 'auto';
            }

            $items[] = [
                'warehouse_id' => $warehouse->id, 'row_no' => $index + 1, 'brand_raw' => $brand, 'raw_name' => mb_substr($name, 0, 500),
                'name_key' => mb_substr($key, 0, 500), 'brand_id' => $brandId, 'kind' => $parsed['kind'], 'gender' => $parsed['gender'],
                'volume_ml' => $parsed['volume'], 'tester' => $parsed['tester'], 'is_set' => $parsed['set'],
                'price' => $price, 'product_variant_id' => $variant, 'matched_by' => $by,
            ];
        }
        if (!$items) {
            throw new RuntimeException('Faylda qiymətli sətir tapılmadı — sütunları yoxlayın.');
        }

        return DB::transaction(function () use ($warehouse, $fileName, $mapping, $actor, $items) {
            $list = WarehousePriceList::create([
                'warehouse_id' => $warehouse->id, 'file_name' => $fileName, 'mapping' => $mapping,
                'rows_count' => count($items), 'created_by' => $actor,
            ]);
            foreach (array_chunk($items, 500) as $chunk) {
                WarehousePriceItem::insert(array_map(fn ($item) => ['price_list_id' => $list->id] + $item, $chunk));
            }

            return $list;
        });
    }

    /** Qiymət: müsbət rəqəm, 2 onluğa yuvarlaqlaşdırılır ("89,262" və "1 250.5" də oxunur) */
    private function price(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim($value));
        }

        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }
}
