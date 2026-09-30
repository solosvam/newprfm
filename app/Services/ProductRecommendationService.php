<?php

namespace App\Services;

use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Tövsiyə olunanlar" — tərkibə (ingredient) görə oxşar ətirlər.
 *
 * Mənbə (seed) məhsullar:
 *  - login olan müştəri: sifariş etdiyi (ləğv olunmamış) + bəyəndiyi məhsullar;
 *  - qonaq: yalnız bəyəndikləri (brauzerdə saxlanır → /recommendations).
 * Mənbə məhsulların özləri tövsiyədə göstərilmir.
 *
 * Bal: namizədin mənbə məhsullarla ortaq tərkib hissələri; hər tərkib hissəsinin çəkisi =
 * onu ehtiva edən mənbə məhsulların sayı (məs. 3 bəyənilən ətirdə "vanil" varsa, vanil 3 bal).
 * Bərabər balda — ortaq tərkib sayı çox olan, sonra daha yeni məhsul.
 */
class ProductRecommendationService
{
    public const MAX_SEEDS = 50;

    /** Müştərinin sifariş etdiyi və bəyəndiyi məhsullar (tövsiyədən çıxarılır). */
    public function seedsForCustomer(Customer $customer): array
    {
        $ordered = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.customer_id', $customer->id)
            ->whereNotIn('orders.order_status_id', DB::table('order_statuses')->where('code', 'cancelled')->select('id'))
            ->whereRaw('order_items.quantity > COALESCE(order_items.cancelled_quantity, 0)')
            ->whereNotNull('order_items.product_id')
            ->orderByDesc('orders.id')
            ->limit(self::MAX_SEEDS)
            ->pluck('order_items.product_id');
        $favorites = DB::table('product_favorites')->where('customer_id', $customer->id)
            ->orderByDesc('created_at')->limit(self::MAX_SEEDS)->pluck('product_id');

        return $this->cleanIds($ordered->merge($favorites)->all());
    }

    /** Qonaqdan gələn id-lər: tam ədəd, təkrarsız, limitli. */
    public function cleanIds(iterable $ids): array
    {
        return collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)
            ->unique()->take(self::MAX_SEEDS)->values()->all();
    }

    /**
     * Mənbə məhsullara tərkibcə ən oxşar aktiv məhsullar (sıralı), mənbələr xaric.
     *
     * @return Collection<int, Product>
     */
    public function similarTo(array $seedIds, int $limit, array $exclude = []): Collection
    {
        if (!$seedIds) {
            return collect();
        }
        $weights = DB::table('product_ingredients')->whereIn('product_id', $seedIds)
            ->groupBy('ingredient_id')->select('ingredient_id', DB::raw('COUNT(*) as weight'));

        $ids = DB::table('product_ingredients as pi')
            ->joinSub($weights, 'sw', 'sw.ingredient_id', '=', 'pi.ingredient_id')
            ->whereNotIn('pi.product_id', array_merge($seedIds, $exclude))
            ->whereIn('pi.product_id', self::available()->select('id'))
            ->groupBy('pi.product_id')
            ->orderByRaw('SUM(sw.weight) DESC')
            ->orderByRaw('COUNT(*) DESC')
            ->orderByDesc('pi.product_id')
            ->limit($limit)
            ->pluck('pi.product_id')->map(fn ($id) => (int) $id)->all();

        return $this->load($ids);
    }

    /** Aktiv, şəkilli, aktiv variantı olan məhsullar */
    public static function available(): Builder
    {
        return Product::query()->where('active', 1)
            ->whereHas('variants', fn ($q) => $q->where('active', 1))
            ->whereHas('images');
    }

    /** Id sırasını saxlayaraq sidebar üçün yükləyir. */
    public function load(array $ids): Collection
    {
        if (!$ids) {
            return collect();
        }
        $products = Product::query()
            ->with(['brand', 'images', 'variants' => fn ($q) => $q->where('active', 1)->orderBy('price'), 'variants.size'])
            ->whereIn('id', $ids)->where('active', 1)
            ->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $products->get($id))
            ->filter(fn ($p) => $p && $p->variants->isNotEmpty())->values();
    }
}
