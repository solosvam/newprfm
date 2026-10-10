<?php

namespace App\Services\PriceList;

use App\Models\PriceList\WarehousePriceItem;
use Illuminate\Support\Facades\DB;

/**
 * Price list sətrini bizim varianta (məhsul + ölçü) bağlayır.
 *  1) yaddaş: operatorun əvvəl təsdiqlədiyi uyğunluq (warehouse_price_matches);
 *  2) avtomatik: brend + təmizlənmiş ad + növ + cins + tester + həcm — yalnız TƏK variant uyğun gələndə.
 * Şübhəli hal (bir neçə namizəd) bağlanmır — operator seçir.
 */
class PriceListMatcher
{
    /** Bizim növ adları → kod (köhnə dublikat adlar da) */
    private const TYPE_KINDS = [
        'EAU DE PARFUM' => 'EDP', 'PARFUM SUYU' => 'EDP', 'EDP' => 'EDP',
        'EAU DE TOILETTE' => 'EDT', 'EDT' => 'EDT',
        'EAU DE COLOGNE' => 'EDC', 'EDC' => 'EDC',
        'EXTRAIT DE PARFUM' => 'EXTRAIT',
        'PARFUM' => 'PARFUM',
    ];


    /** @var array<int, array<string, list<array>>>|null brand_id → ad açarı → məhsullar */
    private ?array $catalog = null;

    /** @var array<string, int>|null brend açarı → brand_id */
    private ?array $brands = null;

    /** @var array<int, string> brand_id → brendin öz adının açarı */
    private array $brandKeys = [];

    /** Fayldakı brend adı → bizim brend: təsdiqlənmiş uyğunluq, eyni ad, axtarış aliası */
    public function brandId(?string $raw): ?int
    {
        $this->brands ??= $this->loadBrands();

        return $this->brands[PriceListNameParser::key($raw)] ?? null;
    }

    public function forgetBrands(): void
    {
        $this->brands = null;
    }

    /**
     * Sətrin avtomatik uyğunlaşdığı variant (yoxdursa və ya şübhəlidirsə — null).
     *
     * @param  array{core: string, kind: ?string, gender: ?string, volume: ?float, tester: bool, set: bool}  $parsed
     */
    public function auto(?int $brandId, array $parsed): ?int
    {
        $ids = array_column($this->candidates($brandId, $parsed, true), 'variant_id');

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * Namizəd variantlar: brend + ad + həcm üst-üstə düşənlər. $strict (avtomatik bağlama) — tester mütləq uyğun olmalı,
     * növ hər iki tərəfdə bilinirsə eyni olmalıdır; cins yalnız bir neçə namizəd qalanda seçim üçün işlədilir
     * (bizim cins məlumatı köhnə importdan gəlir, tam etibarlı deyil — tək namizədi ona görə rədd etmirik).
     *
     * @return list<array{variant_id: int, product_id: int, name: string, size: string, kind: ?string, tester: bool}>
     */
    public function candidates(?int $brandId, array $parsed, bool $strict): array
    {
        if (!$brandId || $parsed['volume'] === null) {
            return [];
        }
        $this->catalog ??= $this->loadCatalog();
        // Adı brendin adı ilə eyni olan məhsul ("AGENT PROVOCATEUR EDP L 200ML") — brend çıxılandan sonra ad boş qalır
        $core = $parsed['core'] !== '' ? $parsed['core'] : ($this->brandKeys[$brandId] ?? '');

        $found = [];
        foreach ($this->catalog[$brandId][$core] ?? [] as $product) {
            if ($strict && $product['tester'] !== $parsed['tester']) continue;
            if ($strict && $parsed['kind'] && $product['kind'] && $parsed['kind'] !== $product['kind']) continue;
            foreach ($product['variants'] as $variant) {
                if ($variant['volume'] !== null && abs($variant['volume'] - $parsed['volume']) < 0.01) {
                    $found[] = ['variant_id' => $variant['id'], 'product_id' => $product['id'], 'name' => $product['name'],
                        'size' => $variant['size'], 'kind' => $product['kind'], 'tester' => $product['tester'], 'genders' => $product['genders']];
                }
            }
        }
        if ($strict && count($found) > 1 && $parsed['gender']) {
            $byGender = array_values(array_filter($found, fn ($c) => in_array($parsed['gender'], $c['genders'], true)));
            $found = $byGender ?: $found;
        }

        return $found;
    }

    /** Siyahının bağlanmamış sətirlərini yenidən yoxlayır (brend uyğunluğu əlavə olunandan sonra) */
    public function rematch(int $priceListId): int
    {
        $matched = 0;
        WarehousePriceItem::where('price_list_id', $priceListId)->whereNull('product_variant_id')->orderBy('id')
            ->chunkById(500, function ($items) use (&$matched) {
                foreach ($items as $item) {
                    $brandId = $this->brandId($item->brand_raw);
                    $variant = $this->auto($brandId, PriceListNameParser::parse($item->raw_name, $item->brand_raw));
                    if ($brandId !== $item->brand_id || $variant) {
                        $item->update(['brand_id' => $brandId, 'product_variant_id' => $variant, 'matched_by' => $variant ? 'auto' : null]);
                        $matched += $variant ? 1 : 0;
                    }
                }
            });

        return $matched;
    }

    private function loadBrands(): array
    {
        $map = [];
        // Zəifdən güclüyə: sonra yazılan əvvəlkini əvəz edir
        foreach (DB::table('search_aliases')->where('type', 'brand')->whereNotNull('brand_id')->get(['alias', 'brand_id']) as $alias) {
            $map[PriceListNameParser::key($alias->alias)] = (int) $alias->brand_id;
        }
        foreach (DB::table('brands')->get(['id', 'name']) as $brand) {
            $map[PriceListNameParser::key($brand->name)] = (int) $brand->id;
        }
        foreach (DB::table('price_list_brand_matches')->get(['brand_key', 'brand_id']) as $match) {
            $map[$match->brand_key] = (int) $match->brand_id;
        }
        unset($map['']);

        return $map;
    }

    private function loadCatalog(): array
    {
        $types = DB::table('types')->pluck('name_az', 'id')->map(fn ($name) => self::TYPE_KINDS[PriceListNameParser::key($name)] ?? null);
        // Cins və "Tester" kateqoriyası ada görə tanınır — id-lər bazadan bazaya fərqlənir ("Kişi" / "Kişi üçün" ayrı sətirlərdir)
        $genderCodes = DB::table('genders')->pluck('name_az', 'id')->map(fn ($name) => match (true) {
            str_contains(PriceListNameParser::key($name), 'UNISEX') => 'U',
            str_contains(PriceListNameParser::key($name), 'QAD') => 'L',
            str_contains(PriceListNameParser::key($name), 'KIS') => 'M',
            default => null,
        });
        $genders = DB::table('product_genders')->get()->groupBy('product_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $genderCodes[$r->gender_id] ?? null)->filter()->unique()->values()->all());
        $testerCategories = DB::table('categories')->pluck('name_az', 'id')->filter(fn ($name) => PriceListNameParser::key($name) === 'TESTER')->keys();
        $testers = DB::table('product_categories')->whereIn('category_id', $testerCategories)->pluck('product_id')->flip();
        $variants = DB::table('product_variants as v')->join('sizes as s', 's.id', '=', 'v.size_id')
            ->get(['v.id', 'v.product_id', 's.name_az'])->groupBy('product_id');

        $this->brandKeys = DB::table('brands')->pluck('name', 'id')->map(fn ($name) => PriceListNameParser::key($name))->all();

        $catalog = [];
        foreach (DB::table('products')->whereNotNull('brand_id')->get(['id', 'brand_id', 'type_id', 'name']) as $product) {
            $parsed = PriceListNameParser::parse($product->name);
            $catalog[$product->brand_id][$parsed['core']][] = [
                'id' => $product->id,
                'name' => $product->name,
                // Köhnə məhsullarda növ / cins / tester adın içindədir ("Si L EDT Tester") — adda yazılan növ sahədəkindən etibarlıdır
                'kind' => $parsed['kind'] ?? ($types[$product->type_id] ?? null),
                'genders' => $genders[$product->id] ?? ($parsed['gender'] ? [$parsed['gender']] : []),
                'tester' => $parsed['tester'] || $testers->has($product->id),
                'variants' => ($variants[$product->id] ?? collect())->map(fn ($v) => [
                    'id' => $v->id, 'size' => $v->name_az,
                    'volume' => preg_match('/^(\d+(?:[.,]\d+)?)\s*ml$/i', trim($v->name_az), $m) ? (float) str_replace(',', '.', $m[1]) : null,
                ])->all(),
            ];
        }

        return $catalog;
    }
}
