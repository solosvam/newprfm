<?php

namespace App\Services;

use App\Models\Order\OrderItemCancellation;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentItem;
use App\Models\Payment\PaymentOperation;
use App\Models\Payment\PaymentRefundItem;
use App\Services\Payment\Birbank;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ləğv olunan məhsulun pulunu karta qaytarır və qaytarmanı konkret ödəniş sətrinə bağlayır.
 *
 *  - Ləğv: pending → processing (bank çağırılır) → refunded.
 *  - Bankdan cavab gəlməsə (timeout) əməliyyat "pending" qalır, ləğv "processing" olur —
 *    heç vaxt avtomatik təkrar göndərilmir (pul iki dəfə qaytarılmasın).
 *  - İdempotent açar ləğvə bağlıdır: düyməni iki dəfə basmaq ikinci qaytarma yaratmır.
 */
class OrderRefundService
{
    public function __construct(private Birbank $birbank) {}

    public function refundCancellation(OrderItemCancellation $cancellation): OrderItemCancellation
    {
        // Əvvəlcə "processing" edirik — eyni anda ikinci klik bankı çağıra bilməsin
        [$cancellation, $payment, $line, $amount] = DB::transaction(function () use ($cancellation) {
            $locked = OrderItemCancellation::whereKey($cancellation->id)->lockForUpdate()->firstOrFail();
            $this->ensure($locked->refund_status !== OrderItemCancellation::REFUND_DONE, 'Bu məbləğ artıq karta qaytarılıb.');
            $this->ensure($locked->refund_status !== OrderItemCancellation::REFUND_PROCESSING, 'Qaytarma bankda yoxlanılır — nəticəni gözləyin.');
            $this->ensure($locked->refund_status === OrderItemCancellation::REFUND_PENDING, 'Bu ləğv üçün karta qaytarma nəzərdə tutulmayıb.');

            $payment = Payment::where('order_id', $locked->order_id)->where('provider', 'birbank')
                ->where('status', Payment::PAID)->latest('id')->first();
            $this->ensure($payment !== null, 'Sifarişin təsdiqlənmiş Birbank ödənişi tapılmadı.');

            // Məhsul — öz sətri; çatdırılma/qablaşdırma (sifarişin tam ləğvi) — həmin növ sətir
            $line = $locked->fee_type
                ? PaymentItem::where('payment_id', $payment->id)->where('type', $locked->fee_type)->first()
                : PaymentItem::where('payment_id', $payment->id)->where('type', 'item')->where('order_item_id', $locked->order_item_id)->first();
            $this->ensure($line !== null, ($locked->fee_type ? 'Bu haqq' : 'Bu məhsul').' ödənişin tərkibində tapılmadı.');

            $amount = round((float) $locked->amount, 2);
            $refundable = round((float) $line->amount - $line->refundedAmount(), 2);
            $this->ensure($amount > 0 && $amount <= $refundable, 'Qaytarılacaq məbləğ bu məhsul üzrə ödənişdən çoxdur ('.number_format($refundable, 2).' AZN qalıb).');

            $locked->update(['refund_status' => OrderItemCancellation::REFUND_PROCESSING]);

            return [$locked, $payment, $line, $amount];
        });

        $key = 'cancellation-refund-'.$cancellation->id;
        try {
            $operation = $this->birbank->refund($payment, number_format($amount, 2, '.', ''), $key);
        } catch (\Throwable $e) {
            $operation = PaymentOperation::where('idempotency_key', $key)->first();
            if (!$operation) {
                // Bank çağırılmayıb (yoxlama xətası) — yenidən cəhd etmək olar
                $cancellation->update(['refund_status' => OrderItemCancellation::REFUND_PENDING]);
                report($e);
                throw ValidationException::withMessages(['refund' => 'Qaytarma alınmadı: '.$e->getMessage()]);
            }
            // Bank çağırılıb, amma nəticə bəlli deyil — təkrar göndərmirik
            $this->link($operation, $line, $cancellation, $amount);
            $cancellation->update(['payment_operation_id' => $operation->id]);
            report($e);
            throw ValidationException::withMessages(['refund' => 'Bankdan cavab alınmadı. Qaytarma "bankda yoxlanılır" vəziyyətindədir — təkrar göndərməyin, Birbank kabinetindən yoxlayın.']);
        }

        $this->link($operation, $line, $cancellation, $amount);
        $done = $operation->status === 'succeeded';
        if ($done) {
            // Kassa: Onlayn ödənişlər → Müştəri
            rescue(fn () => app(FinanceService::class)->recordOnlineRefund($operation));
        }
        $cancellation->update([
            'refund_status' => $done ? OrderItemCancellation::REFUND_DONE : OrderItemCancellation::REFUND_PROCESSING,
            'payment_operation_id' => $operation->id,
            'refunded_at' => $done ? now() : null,
        ]);

        return $cancellation->fresh();
    }

    private function link(PaymentOperation $operation, PaymentItem $line, OrderItemCancellation $cancellation, float $amount): void
    {
        PaymentRefundItem::firstOrCreate(
            ['payment_operation_id' => $operation->id, 'payment_item_id' => $line->id],
            ['order_item_cancellation_id' => $cancellation->id, 'quantity' => $cancellation->quantity, 'amount' => $amount],
        );
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages(['refund' => $message]);
        }
    }
}
