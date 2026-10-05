<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Order\OrderItemCancellation;
use App\Models\Procurement\AllocationStatusLog;
use App\Models\Procurement\OrderItemAllocation;
use App\Services\Payment\PaymentItemsBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Məhsul / miqdar üzrə ləğv (müştəri ilə razılaşdırılıb, kuryer təyin edilməzdən əvvəl).
 *
 *  - Məhsul silinmir: order_items.cancelled_quantity artır, ləğv ayrıca qeyd olunur.
 *  - Yekun azalır: vahid qiymət × say − həmin miqdara düşən promo payı (PaymentItemsBuilder ilə eyni paylama).
 *    Çatdırılma və qablaşdırma dəyişmir.
 *  - Onlayn ödənilibsə: qaytarılacaq məbləğ "pending" qalır (pulun qaytarılması — ayrıca addım).
 *    Bonusla ödənilibsə: məbləğ dərhal bonus balansına qayıdır. Nağd: kuryerin alacağı məbləğ azalır.
 *  - Qazanılmış bonus artıq yazılıbsa, fərqi geri alınır ("Sifariş ləğvi" qeydi ilə).
 *  - Artıq qalan anbar seçimləri (ən yenisindən) bağlanır.
 *
 * Qapıda imtina (refuseAtDoor): kuryer ünvandadır, müştəri məhsullardan birini götürmür.
 *  Eyni hesablama; götürülmüş anbar hissəsi ləğv edilmir — "Anbara qaytarılır" olur (kuryer sonra "Qaytardım").
 *  Kuryerin alacağı nağd məbləğ yeni yekundur.
 */
class OrderItemCancellationService
{
    /** Kuryer təyin edilməzdən əvvəlki mərhələlər */
    public const EDITABLE_STATUSES = ['new', 'confirmed', 'preparing', 'warehouse_requested', 'warehouses_assigned'];

    /** Qapıda imtina — kuryer yoldadır / ünvandadır */
    public const DOOR_STATUSES = ['sent', 'at_address'];

    public function __construct(
        private PaymentItemsBuilder $shares,
        private BonusService $bonus,
        private ProcurementService $procurement,
    ) {}

    /**
     * Ləğv mümkün deyilsə səbəbi, mümkündürsə null.
     * Sifariş səviyyəsində yoxlanır (məhsul səviyyəsi cancel()-də).
     */
    public function blockReason(Order $order): ?string
    {
        $order->loadMissing(['status', 'paymentMethod']);

        return match (true) {
            !in_array($order->status?->code, self::EDITABLE_STATUSES, true) => 'Bu mərhələdə məhsul ləğv edilə bilməz.',
            $order->paymentMethod?->code === 'installment' => 'Hissə-hissə (kredit) sifarişində məhsul ləğvi hələ dəstəklənmir.',
            $order->hasPendingPayment() => 'Bankda nəticəsi bəlli olmayan ödəniş var — nəticəni gözləyin.',
            default => null,
        };
    }

    /** Qapıda imtina mümkün deyilsə səbəbi */
    public function doorBlockReason(Order $order): ?string
    {
        $order->loadMissing(['status', 'paymentMethod']);

        return match (true) {
            !in_array($order->status?->code, self::DOOR_STATUSES, true) => 'Qapıda imtina yalnız kuryer yolda və ya ünvanda olanda qeyd olunur.',
            $order->paymentMethod?->code === 'installment' => 'Hissə-hissə (kredit) sifarişində qapıda imtina hələ dəstəklənmir.',
            $order->hasPendingPayment() => 'Bankda nəticəsi bəlli olmayan ödəniş var — nəticəni gözləyin.',
            default => null,
        };
    }

    /**
     * Ləğvin nəticəsi (bazaya yazmadan): yekundan çıxılan, yeni yekun, bonus düzəlişi, qaytarma.
     * Modalda 1..aktiv say üçün göstərilir və cancel() da məhz bunu tətbiq edir.
     *
     * @return array{amount: float, subtotal: float, discount: float, total: float, bonus: float, refund: ?string}
     */
    public function preview(Order $order, OrderItem $item, int $quantity): array
    {
        $cents = fn ($value) => (int) round((float) $value * 100);
        $active = $item->activeQuantity();

        $row = collect($this->shares->itemShares($order))->first(fn ($r) => $r['item']->id === $item->id);
        $promoPart = $row ? ($quantity === $active ? $row['promo'] : intdiv($row['promo'] * $quantity, $active)) : 0;
        $unit = $cents($item->unit_price);
        $base = $cents($item->list_price ?? $item->unit_price);

        $amount = $unit * $quantity - $promoPart;
        $subtotal = $cents($order->subtotal) - $base * $quantity;
        $discount = max(0, $cents($order->discount) - ($base - $unit) * $quantity - $promoPart);
        $total = max(0, $cents($order->total) - $amount);

        // Bonus artıq yazılıbsa, yeni məbləğə görə fərq geri alınır
        $bonus = 0.0;
        if ((float) $order->bonus_earned > 0) {
            $after = (clone $order)->forceFill(['subtotal' => $subtotal / 100, 'discount' => $discount / 100]);
            $bonus = max(0, round((float) $order->bonus_earned - $this->bonus->amountForOrder($after), 2));
        }

        return [
            'amount' => $amount / 100,
            'subtotal' => $subtotal / 100,
            'discount' => $discount / 100,
            'total' => $total / 100,
            'bonus' => $bonus,
            'refund' => $this->refundStatus($order),
        ];
    }

    public function cancel(Order $order, OrderItem $item, int $quantity, string $reason, ?string $note, int $actor): OrderItemCancellation
    {
        return $this->apply($order, $item, $quantity, $reason, $note, $actor, false);
    }

    /** Qapıda imtina — operator (CRM) və ya kuryer (öz səhifəsi) */
    public function refuseAtDoor(Order $order, OrderItem $item, int $quantity, ?string $note, int $actor): OrderItemCancellation
    {
        return $this->apply($order, $item, $quantity, 'door_refused', $note, $actor, true);
    }

    private function apply(Order $order, OrderItem $item, int $quantity, string $reason, ?string $note, int $actor, bool $door): OrderItemCancellation
    {
        return DB::transaction(function () use ($order, $item, $quantity, $reason, $note, $actor, $door) {
            $order = Order::with(['items', 'status', 'paymentMethod', 'customer'])->lockForUpdate()->findOrFail($order->id);
            $item = $order->items->firstWhere('id', $item->id);

            $this->ensure($item !== null, 'Məhsul bu sifarişə aid deyil.');
            $this->ensure(($block = $door ? $this->doorBlockReason($order) : $this->blockReason($order)) === null, (string) $block);
            $this->ensure(isset(OrderItemCancellation::REASONS[$reason]), 'Səbəbi seçin.');
            $this->ensure($quantity >= 1 && $quantity <= $item->activeQuantity(), 'Ləğv edilən say qalan miqdardan çox ola bilməz.');
            $remaining = $order->items->sum(fn ($i) => $i->activeQuantity()) - $quantity;
            $this->ensure($remaining > 0, $door
                ? 'Müştəri bütün məhsullardan imtina edirsə, "Problem" bildirin — operator sifarişi ləğv edəcək.'
                : 'Sifarişdə ən azı bir məhsul qalmalıdır. Hamısı ləğv olunursa, sifarişi ləğv edin.');

            $result = $this->preview($order, $item, $quantity);

            if ($door) {
                $this->returnPicked($item, $quantity, $note, $actor);
            } else {
                // Artıq qalan anbar seçimləri: ən yenisindən bağlanır
                $keep = $item->activeQuantity() - $quantity;
                $allocations = OrderItemAllocation::where('order_item_id', $item->id)->where('status', '!=', 'cancelled')->orderByDesc('id')->get();
                $selected = (int) $allocations->sum('quantity');
                foreach ($allocations as $allocation) {
                    if ($selected <= $keep) break;
                    $this->procurement->cancelAllocation($order, $allocation->id, 'Məhsul ləğv edildi: '.$quantity.' ədəd', $actor);
                    $selected -= (int) $allocation->quantity;
                }
            }

            $item->update([
                'cancelled_quantity' => (int) $item->cancelled_quantity + $quantity,
                'total' => round((float) $item->unit_price * ($item->activeQuantity() - $quantity), 2),
            ]);
            $order->update([
                'subtotal' => $result['subtotal'],
                'discount' => $result['discount'],
                'total' => $result['total'],
            ]);

            $productName = ($item->product?->name ?? 'Məhsul').' ×'.$quantity;

            // Qazanılmış bonusun fərqi geri alınır
            if ($result['bonus'] > 0 && $order->customer) {
                DB::table('customers')->where('id', $order->customer_id)->decrement('bonus_balance', $result['bonus']);
                $order->customer->bonusTransactions()->create([
                    'order_id' => $order->id, 'type' => 'adjustment', 'amount' => -$result['bonus'],
                    'note' => 'Sifariş ləğvi: '.$order->order_no.' · '.$productName, 'created_by' => $actor,
                ]);
                $order->update(['bonus_earned' => max(0, round((float) $order->bonus_earned - $result['bonus'], 2))]);
            }

            // Bonusla ödənilib: ləğv olunan məbləğ bonus balansına qayıdır
            if ($result['refund'] === OrderItemCancellation::REFUND_BONUS && $order->customer) {
                DB::table('customers')->where('id', $order->customer_id)->increment('bonus_balance', $result['amount']);
                $order->customer->bonusTransactions()->create([
                    // refund — yeni bonus paketi deyil: xərclənmiş bonus öz köhnə bitmə tarixinə qayıdır
                    'order_id' => $order->id, 'type' => 'refund', 'amount' => $result['amount'],
                    'note' => 'Ləğv olunan sifarişdən geri qaytarma: '.$order->order_no.' · '.$productName, 'created_by' => $actor,
                ]);
            }

            $cancellation = OrderItemCancellation::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'quantity' => $quantity,
                'amount' => $result['amount'],
                'reason' => $reason,
                'note' => $note,
                'customer_agreed' => true,
                'bonus_adjustment' => $result['bonus'],
                'refund_status' => $result['refund'],
                'created_by' => $actor,
            ]);
            if ($door) {
                // Sifariş səhifəsində "Kuryer bildirişləri"ndə görünür
                DB::table('order_status_logs')->insert([
                    'order_id' => $order->id, 'status_id' => $order->order_status_id, 'user_id' => $actor, 'kind' => 'door_refusal',
                    'note' => 'Qapıda imtina: '.$productName.' — '.number_format($result['amount'], 2).' AZN. Yeni yekun: '
                        .number_format($result['total'], 2).' AZN.'.($note ? ' '.$note : ''),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                // Qalan miqdarın hamısı seçilibsə "Anbarlar təyin olundu"
                app(OrderStatusService::class)->syncSupply($order, $actor);
            }

            return $cancellation;
        });
    }

    /**
     * Qapıda imtina: götürülmüş hissələrdən (ən yenisindən) imtina edilən say "Anbara qaytarılır" olur.
     * Hissənin bir qismi qaytarılırsa, hissə bölünür (qalan — götürülüb, ayrılan — qaytarılır).
     */
    private function returnPicked(OrderItem $item, int $quantity, ?string $note, int $actor): void
    {
        $parts = OrderItemAllocation::where('order_item_id', $item->id)->where('status', OrderItemAllocation::PICKED)
            ->lockForUpdate()->orderByDesc('id')->get();
        $this->ensure((int) $parts->sum('quantity') >= $quantity, 'Bu məhsuldan kuryerdə '.(int) $parts->sum('quantity').' ədəd var.');

        $left = $quantity;
        foreach ($parts as $part) {
            if ($left <= 0) break;
            $take = min($left, (int) $part->quantity);
            if ($take === (int) $part->quantity) {
                $part->update(['status' => OrderItemAllocation::RETURNING]);
                $target = $part;
            } else {
                $part->update(['quantity' => (int) $part->quantity - $take]);
                $target = $part->replicate();
                $target->forceFill(['quantity' => $take, 'status' => OrderItemAllocation::RETURNING, 'idempotency_key' => (string) Str::uuid()])->save();
            }
            AllocationStatusLog::create([
                'order_item_allocation_id' => $target->id, 'from_status' => OrderItemAllocation::PICKED,
                'to_status' => OrderItemAllocation::RETURNING, 'user_id' => $actor,
                'note' => 'Qapıda imtina: '.$take.' ədəd'.($target->id !== $part->id ? ' (#'.$part->id.'-dən ayrıldı)' : '').($note ? ' — '.$note : ''),
                'created_at' => now(),
            ]);
            $left -= $take;
        }
    }

    /** Pul artıq alınıbsa necə qaytarılacaq; alınmayıbsa (nağd, ödənilməmiş onlayn) null */
    private function refundStatus(Order $order): ?string
    {
        if ($order->payment_status !== 'paid') {
            return null;
        }

        return match ($order->paymentMethod?->code) {
            'card_online', 'birbank_installment' => OrderItemCancellation::REFUND_PENDING,
            'bonus_balance' => OrderItemCancellation::REFUND_BONUS,
            default => null,
        };
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages(['cancel' => $message]);
        }
    }
}
