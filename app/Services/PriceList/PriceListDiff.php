<?php

namespace App\Services\PriceList;

use App\Models\PriceList\WarehousePriceItem;
use App\Models\PriceList\WarehousePriceList;
use Illuminate\Support\Collection;

/**
 * Deponun price list-i bundan əvvəlki siyahısı ilə müqayisə olunur:
 *  - added — yenidə var, köhnədə yox idi;
 *  - removed — köhnədə var idi, yenidə yoxdur;
 *  - changed — hər ikisində var, alış qiyməti dəyişib.
 * Sətirlər əvvəl bizim variantla (hər ikisi bağlıdırsa — depo adı bir az dəyişsə də eyni məhsul sayılır),
 * sonra normallaşdırılmış adla (name_key) cütlənir. Siyahıda eyni açar bir neçə dəfədirsə, sıra ilə cütlənir.
 */
class PriceListDiff
{
    public const TYPES = ['added', 'removed', 'changed'];

    /** Eyni deponun bundan əvvəlki siyahısı; yoxdursa null */
    public function previous(WarehousePriceList $list): ?WarehousePriceList
    {
        return WarehousePriceList::where('warehouse_id', $list->warehouse_id)->where('id', '<', $list->id)->orderByDesc('id')->first();
    }

    /**
     * @return array{previous: WarehousePriceList, added: Collection, removed: Collection,
     *               changed: Collection<int, array{old: WarehousePriceItem, new: WarehousePriceItem}>}|null
     */
    public function compare(WarehousePriceList $list): ?array
    {
        if (!$previous = $this->previous($list)) {
            return null;
        }
        $columns = ['id', 'row_no', 'brand_raw', 'raw_name', 'name_key', 'price', 'product_variant_id'];
        $old = $previous->items()->orderBy('row_no')->get($columns);
        $new = $list->items()->orderBy('row_no')->get($columns);

        $pairs = [];
        $leftOld = $old;
        $leftNew = $new;
        // 1) bizim variantla, 2) adla
        foreach ([fn ($i) => $i->product_variant_id ? 'v'.$i->product_variant_id : null, fn ($i) => 'n'.$i->name_key] as $key) {
            $queues = [];
            foreach ($leftOld as $item) {
                if (($k = $key($item)) !== null) {
                    $queues[$k][] = $item;
                }
            }
            $usedOld = [];
            $restNew = collect();
            foreach ($leftNew as $item) {
                $k = $key($item);
                if ($k !== null && !empty($queues[$k])) {
                    $match = array_shift($queues[$k]);
                    $usedOld[$match->id] = true;
                    $pairs[] = ['old' => $match, 'new' => $item];
                } else {
                    $restNew->push($item);
                }
            }
            $leftOld = $leftOld->reject(fn ($i) => isset($usedOld[$i->id]))->values();
            $leftNew = $restNew;
        }

        $changed = collect($pairs)
            ->filter(fn ($p) => abs((float) $p['new']->price - (float) $p['old']->price) >= 0.005)
            // ən böyük faiz dəyişikliyi əvvəl
            ->sortByDesc(fn ($p) => abs(self::percent($p['old'], $p['new'])))
            ->values();

        return ['previous' => $previous, 'added' => $leftNew->values(), 'removed' => $leftOld, 'changed' => $changed];
    }

    /** @return array{added: int, removed: int, changed: int}|null */
    public function counts(WarehousePriceList $list): ?array
    {
        $diff = $this->compare($list);

        return $diff ? ['added' => $diff['added']->count(), 'removed' => $diff['removed']->count(), 'changed' => $diff['changed']->count()] : null;
    }

    public static function percent(WarehousePriceItem $old, WarehousePriceItem $new): float
    {
        return (float) $old->price > 0 ? round(((float) $new->price - (float) $old->price) / (float) $old->price * 100, 1) : 0.0;
    }
}
