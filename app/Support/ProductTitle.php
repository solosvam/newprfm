<?php

namespace App\Support;

use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;

/**
 * Admin paneli və kuryer tətbiqində məhsulun adı bir qaydada: Brend, Model, Cins, Növ, Ölçü
 *   "Narciso Rodriguez Pure Musc For Her L EDP 50ml"
 *  - Cins: Qadın üçün → L, Kişi üçün → M, Unisex → U (cinsi yazılmayıbsa — buraxılır)
 *  - Növ: Eau De Parfum → EDP, Eau De Toilette → EDT, Extrait De Parfum → Extrait, Eau De Cologne → EDC,
 *    Parfum → Parfum; qalanları (Body Lotion, Shower Gel...) olduğu kimi
 *  - Model adı brendlə başlayırsa ("Agent Provocateur" → "Agent Provocateur"), brend təkrarlanmır
 * Əlaqələr (brand, type, genders, variant.size) çağıran tərəfdən yüklənməlidir — N+1 olmasın.
 */
final class ProductTitle
{
    public const TYPES = [
        'eau de parfum' => 'EDP',
        'eau de toilette' => 'EDT',
        'extrait de parfum' => 'Extrait',
        'eau de cologne' => 'EDC',
        'parfum' => 'Parfum',
    ];

    public const PRODUCT_RELATIONS = ['brand', 'type', 'genders'];

    public static function of(?Product $product, ?string $size = null, string $missing = 'Silinmiş məhsul'): string
    {
        if (!$product) {
            return $missing;
        }
        $brand = trim(html_entity_decode((string) $product->brand?->name, ENT_QUOTES));
        $model = trim(html_entity_decode((string) $product->name, ENT_QUOTES));
        if ($brand !== '' && mb_stripos($model, $brand) === 0) {
            $brand = '';
        }

        return collect([$brand, $model, self::gender($product), self::type($product), self::size($size)])
            ->filter(fn ($part) => $part !== null && $part !== '')->implode(' ');
    }

    public static function variant(?ProductVariant $variant, string $missing = 'Silinmiş məhsul'): string
    {
        return self::of($variant?->product, $variant?->size?->name_az, $missing);
    }

    /** Sifariş sətri: məhsul + sifarişdəki ölçü */
    public static function item(?OrderItem $item, string $missing = 'Silinmiş məhsul'): string
    {
        return self::of($item?->product, $item?->variant?->size?->name_az, $missing);
    }

    /** Sifariş sətirlərinin göstərilməsi üçün yüklənəcək əlaqələr */
    public static function itemRelations(string $prefix = ''): array
    {
        return [$prefix.'product.brand', $prefix.'product.type', $prefix.'product.genders', $prefix.'variant.size'];
    }

    /**
     * Sorğu sətirlərinə (stdClass: product_id, size) "title" əlavə edir — məhsullar bir sorğu ilə yüklənir.
     * Hesabat / siyahılarda DB::table ilə seçilən sətirlər üçün.
     */
    public static function fill(iterable $rows, string $idKey = 'product_id', string $sizeKey = 'size'): void
    {
        $rows = collect($rows);
        $ids = $rows->pluck($idKey)->filter()->unique()->values();
        $products = $ids->isEmpty() ? collect() : Product::with(self::PRODUCT_RELATIONS)->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($rows as $row) {
            $id = is_array($row) ? ($row[$idKey] ?? null) : ($row->{$idKey} ?? null);
            $size = is_array($row) ? ($row[$sizeKey] ?? null) : ($row->{$sizeKey} ?? null);
            if (is_object($row)) {
                $row->title = self::of($products[$id] ?? null, $size);
            }
        }
    }

    public static function gender(Product $product): ?string
    {
        $codes = $product->genders->map(function ($g) {
            $name = mb_strtolower((string) $g->name_az.' '.$g->name_en);

            return match (true) {
                str_contains($name, 'unisex') => 'U',
                str_contains($name, 'qadın') || str_contains($name, 'women') => 'L',
                str_contains($name, 'kişi') || str_contains($name, 'men') => 'M',
                default => null,
            };
        })->filter()->unique()->values();

        // Həm kişi, həm qadın işarələnibsə — uniseks
        return $codes->count() > 1 ? 'U' : $codes->first();
    }

    public static function type(Product $product): ?string
    {
        $type = $product->type;
        if (!$type) {
            return null;
        }

        return self::TYPES[mb_strtolower(trim((string) ($type->name_en ?: $type->name_az)))] ?? $type->name_az;
    }

    /** "50 ml" → "50ml" */
    public static function size(?string $size): ?string
    {
        return $size ? preg_replace('/\s+/u', '', $size) : null;
    }
}
