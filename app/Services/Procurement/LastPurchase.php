<?php

namespace App\Services\Procurement;

use App\Models\Procurement\OrderItemAllocation;
use Illuminate\Support\Facades\DB;

/**
 * Ölçünün son anbar alışı: son "Götürülüb" təminat hissəsi — qiymət, anbar, götürülmə tarixi.
 * Yalnız CRM-də, "crm.purchase_info" icazəsi ilə göstərilir (saytda yox).
 */
final class LastPurchase
{
    /** @return array<int, array{cost: float, warehouse: ?string, date: string}> ölçü id → son alış */
    public static function forVariants(array $variantIds): array
    {
        if (!$variantIds) {
            return [];
        }

        $rows = DB::table('order_item_allocations as a')
            ->join('order_items as oi', 'oi.id', '=', 'a.order_item_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'a.warehouse_id')
            ->where('a.status', OrderItemAllocation::PICKED)
            ->whereIn('oi.product_variant_id', $variantIds)
            ->select('oi.product_variant_id as variant_id', 'a.unit_cost', 'w.name_az as warehouse', 'a.updated_at')
            ->selectSub(fn ($q) => $q->from('allocation_status_logs as l')->whereColumn('l.order_item_allocation_id', 'a.id')
                ->where('l.to_status', OrderItemAllocation::PICKED)->selectRaw('max(l.created_at)'), 'picked_at')
            ->get();

        return $rows->groupBy('variant_id')->map(function ($group) {
            $last = $group->sortByDesc(fn ($r) => $r->picked_at ?? $r->updated_at)->first();

            return [
                'cost' => (float) $last->unit_cost,
                'warehouse' => $last->warehouse,
                'date' => \Illuminate\Support\Carbon::parse($last->picked_at ?? $last->updated_at)->format('d.m.Y'),
            ];
        })->all();
    }
}
