<?php

namespace App\Services;

use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\Payment\PaymentMethod;
use App\Models\Payment\Payment;
use App\Models\PromoCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operator sifarişin ödəniş üsulunu dəyişir: Qapıda ödəniş (cash) ↔ Saytda onlayn ödəniş (card_online).
 *  - sifariş ləğv / təhvil edilməyib, pulu alınmayıb (ödənilib deyil, müştəri ödənişi yazılmayıb);
 *  - onlayn → qapıda: bankda yarımçıq ödəniş olmamalıdır; ödəniş linki bağlanır;
 *  - qapıda → onlayn: kuryer pul almır (payment_status pending), link CRM-dən SMS / kopyala ilə gedir.
 * Promokodun istifadə sayı: nağdda sifariş yarananda, onlaynda bank təsdiqləyəndə artır — burada uyğunlaşdırılır.
 * Dəyişiklik sifariş tarixçəsinə yazılır (order_status_logs.kind = payment_change).
 */
class OrderPaymentMethodChange
{
    public const CODES = ['cash', 'card_online'];

    /** Dəyişməyə mane olan səbəb; null — dəyişmək olar */
    public function block(Order $order): ?string
    {
        $order->loadMissing(['paymentMethod', 'status']);

        return match (true) {
            !in_array($order->paymentMethod?->code, self::CODES, true) => 'Yalnız qapıda ödəniş ilə onlayn ödəniş arasında dəyişmək olar.',
            $order->isCancelled() => 'Sifariş ləğv edilib.',
            $order->status?->code === 'delivered' => 'Sifariş artıq təhvil verilib.',
            $order->payment_status === 'paid' => 'Sifariş artıq ödənilib.',
            $order->payments()->where('status', Payment::PENDING)->exists() => 'Bankda nəticəsi bəlli olmayan ödəniş var — əvvəlcə "Bankdan yoxla".',
            MoneyMovement::where('order_id', $order->id)->where('kind', 'customer_payment')->exists() => 'Müştərinin ödənişi artıq yazılıb.',
            default => null,
        };
    }

    public function change(Order $order, string $code, ?int $actor): Order
    {
        return DB::transaction(function () use ($order, $code, $actor) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($reason = $this->block($locked)) {
                throw ValidationException::withMessages(['payment_method' => $reason]);
            }
            $from = $locked->paymentMethod;
            if ($from->code === $code) {
                throw ValidationException::withMessages(['payment_method' => 'Sifarişin ödəniş üsulu artıq budur.']);
            }
            $to = PaymentMethod::where('code', $code)->firstOrFail();

            if ($code === 'card_online') {
                // Əvvəlki uğursuz cəhdlər varsa — "ləğv edilib" ki, müştəri yenidən ödəyə bilsin (Order::canStartOnlinePayment)
                $status = $locked->payments()->exists() ? 'cancelled' : 'pending';
                $locked->forceFill(['payment_method_id' => $to->id, 'payment_status' => $status, 'birbank_installment_months' => null])->save();
                if ($locked->promo_code_id) {
                    PromoCode::whereKey($locked->promo_code_id)->where('used_count', '>', 0)->decrement('used_count');
                }
            } else {
                // Köhnə ödəniş linki işləməsin
                $locked->forceFill(['payment_method_id' => $to->id, 'payment_status' => 'cod', 'pay_token_expires_at' => null])->save();
                if ($locked->promo_code_id) {
                    PromoCode::whereKey($locked->promo_code_id)->increment('used_count');
                }
            }

            DB::table('order_status_logs')->insert([
                'order_id' => $locked->id, 'status_id' => $locked->order_status_id, 'user_id' => $actor, 'kind' => 'payment_change',
                'note' => 'Ödəniş üsulu: '.$from->name_az.' → '.$to->name_az,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $locked->fresh(['paymentMethod', 'status', 'customer']);
        });
    }
}
