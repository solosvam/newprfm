<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\PriceAlert;
use App\Models\Product\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Qiymət enəndə xəbər ver" — seçilmiş ölçüyə abunə ol / abunəliyi ləğv et (yalnız daxil olmuş müştəri) */
class PriceAlertController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate(['variant_id' => ['required', 'integer']]);
        $variant = ProductVariant::with('product.activeDiscount')->whereKey($data['variant_id'])->where('active', 1)
            ->whereHas('product', fn ($q) => $q->where('active', 1))->firstOrFail();
        $customerId = (int) $request->user()->id;

        $existing = PriceAlert::where('customer_id', $customerId)->where('product_variant_id', $variant->id)->first();
        // Aktiv abunəlik — ləğv; yoxdursa və ya artıq xəbər verilib — indiki qiymətlə yenidən abunə
        if ($existing && !$existing->notified_at) {
            $existing->delete();

            return response()->json(['subscribed' => false, 'message' => __('price_alert_unsubscribed')]);
        }
        PriceAlert::updateOrCreate(
            ['customer_id' => $customerId, 'product_variant_id' => $variant->id],
            ['price' => $variant->salePrice(), 'notified_at' => null], // endirimdədirsə — endirimli qiymətdən aşağı düşəndə
        );

        return response()->json(['subscribed' => true, 'message' => __('price_alert_subscribed')]);
    }
}
