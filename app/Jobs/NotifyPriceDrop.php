<?php

namespace App\Jobs;

use App\Models\Product\PriceAlert;
use App\Models\Product\ProductVariant;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Variantın qiyməti endi (ProductVariant::updated): abunə qiyməti yeni qiymətdən yüksək olan, hələ xəbər almamış
 * müştərilərə push. Göndəriləndən sonra notified_at — eyni abunəliyə ikinci dəfə getmir.
 * Push qurulmayıbsa (REST açarı yoxdur), abunəliklər açıq qalır — növbəti enişdə yenə yoxlanılır.
 */
class NotifyPriceDrop implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $variantId) {}

    public function handle(PushService $push): void
    {
        $variant = ProductVariant::with(['product.brand', 'product.activeDiscount', 'size'])->find($this->variantId);
        if (!$variant || !$variant->active || !$variant->product?->active) {
            return;
        }
        $price = $variant->salePrice(); // məhsul endirimi də nəzərə alınır

        $alerts = PriceAlert::where('product_variant_id', $variant->id)
            ->whereNull('notified_at')
            ->where('price', '>', $price)
            ->get(['id', 'customer_id']);
        if ($alerts->isEmpty()) {
            return;
        }

        $name = trim($variant->product->brand?->name.' '.$variant->product->name.' '.$variant->size?->name_az);
        $sent = $push->toCustomers(
            $alerts->pluck('customer_id')->all(),
            __('price_alert_push_title'),
            __('price_alert_push_text', ['name' => $name, 'price' => number_format($price, 2)]),
            route('product', $variant->product->slug),
        );

        if ($sent) {
            PriceAlert::whereKey($alerts->pluck('id'))->update(['notified_at' => now()]);
        }
    }
}
