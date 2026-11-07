<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sifariş statusunun axını:
 *   new → (İcraya götür) preparing → (ilk sorğu) warehouse_requested
 *   → (bütün miqdar depoya seçildi) warehouses_assigned → (Kuryer təyin et) courier_assigned
 *   → sent → delivered   (kuryer — 6-cı addım)
 * Seçim ləğv olunub çatışmazlıq yaranarsa warehouses_assigned → warehouse_requested.
 */
class OrderStatusService
{
    /** Depo sorğusu, cavab, seçim və seçimin ləğvi bu mərhələlərdə mümkündür */
    public const PROCUREMENT = ['preparing', 'warehouse_requested', 'warehouses_assigned'];

    /** Təminat hissəsinin mərhələləri (bildirildi/ayrıldı/götürüldü) — kuryer təyin olunandan sonra da */
    public const SUPPLY_FLOW = ['preparing', 'warehouse_requested', 'warehouses_assigned', 'courier_assigned'];

    public const ONLINE_METHODS = ['card_online', 'birbank_installment'];

    /** Statusu dəyişir və tarixçəyə yazır. Artıq həmin statusdadırsa — heç nə. */
    public function set(Order $order, string $code, ?int $actor, ?string $note = null): bool
    {
        $status = OrderStatus::where('code', $code)->first();
        if (!$status) {
            throw ValidationException::withMessages(['status' => 'Status tapılmadı: '.$code.' (php artisan migrate)']);
        }
        if ((int) $order->order_status_id === (int) $status->id) {
            return false;
        }
        $order->forceFill(['order_status_id' => $status->id])->save();
        $order->setRelation('status', $status);
        DB::table('order_status_logs')->insert([
            'order_id' => $order->id, 'status_id' => $status->id, 'user_id' => $actor,
            'note' => $note, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Kredit müraciəti sifarişlə birlikdə: icraya götürüldü → "Təsdiqlənib", Parfumshop ləğv etdi → "Ləğv edilib".
        // ("İmtina edilib" — Ferrum-un cavabıdır, yalnız əl ilə qoyulur.)
        if (in_array($code, ['preparing', 'cancelled'], true) && $order->loadMissing('paymentMethod')->paymentMethod?->code === 'installment'
            && ($credit = $order->creditApplication()->first())) {
            app(\App\Services\Credit\CreditStatusService::class)
                ->set($credit, $code === 'preparing' ? 'approved' : 'cancelled', $actor, 'order');
        }
        if ($code === 'delivered') {
            app(BonusService::class)->earnOnDelivery($order); // sifariş bonusu — yalnız təhvildə
            app(ReferralService::class)->rewardForDeliveredOrder($order); // dəvət olunanın ilk sifarişi → referal bonusları
            if ($order->loadMissing('paymentMethod')->paymentMethod?->code === 'installment') {
                app(FinanceService::class)->recordCreditReceivable($order, $actor); // Ferrum-un köçürəcəyi pul — Ferrum hesabına
            }
        }

        return true;
    }

    /** "İcraya götür"ə mane olan səbəb (yoxdursa null) */
    public function startBlock(Order $order): ?string
    {
        $order->loadMissing(['status', 'paymentMethod']);

        return match (true) {
            $order->status?->code !== 'new' => 'Sifariş artıq icradadır.',
            !$order->customer_id => 'Əvvəlcə sifarişi müştəriyə bağlayın.',
            in_array($order->paymentMethod?->code, self::ONLINE_METHODS, true) && $order->payment_status !== 'paid'
                => 'Onlayn ödəniş hələ edilməyib — ödənişdən sonra icraya götürmək olar.',
            default => null,
        };
    }

    public function start(Order $order, int $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $locked = Order::with(['status', 'paymentMethod'])->lockForUpdate()->findOrFail($order->id);
            if (($block = $this->startBlock($locked)) !== null) {
                throw ValidationException::withMessages(['status' => $block]);
            }
            $this->set($locked, 'preparing', $actor);
        });
    }

    /**
     * Depo dəyişikliklərindən sonra (sorğu, seçim, seçimin ləğvi, məhsul ləğvi) statusu uyğunlaşdırır.
     * Sifariş kilidli tranzaksiya daxilində çağırılır.
     */
    public function syncSupply(Order $order, ?int $actor): void
    {
        $order->load(['status', 'items.allocations']);
        $code = $order->status?->code;
        if (!in_array($code, self::PROCUREMENT, true)) {
            return;
        }
        $items = $order->items->filter(fn ($item) => $item->activeQuantity() > 0);
        $allAllocated = $items->isNotEmpty() && $items->every(fn ($item) =>
            (int) $item->allocations->where('status', '!=', OrderItemAllocation::CANCELLED)->sum('quantity') >= $item->activeQuantity());
        $hasRequests = DB::table('warehouse_requests')->where('order_id', $order->id)->exists();

        if ($allAllocated) {
            $this->set($order, 'warehouses_assigned', $actor);
        } elseif ($code === 'warehouses_assigned' || ($code === 'preparing' && $hasRequests)) {
            $this->set($order, 'warehouse_requested', $actor, $code === 'warehouses_assigned' ? 'Depo seçimi çatışmır' : null);
        }
    }

    /** "Kuryer təyin et"ə mane olan səbəb */
    public function courierBlock(Order $order): ?string
    {
        $order->loadMissing(['status', 'items.allocations']);
        $picked = $order->items->flatMap->allocations->contains('status', OrderItemAllocation::PICKED);

        if ($order->status?->code === 'new') {
            return 'Əvvəlcə sifarişi icraya götürün.';
        }
        if ($order->status?->code === 'courier_assigned') {
            return $picked ? 'Kuryer məhsul götürüb — dəyişmək olmaz.' : null;
        }
        if ($order->status?->code === 'warehouses_assigned') {
            return null;
        }
        if (!in_array($order->status?->code, self::PROCUREMENT, true)) {
            return 'Bu mərhələdə kuryer dəyişdirilmir.';
        }
        // Hansı məhsulda nə qədər depoya seçilməyib
        $order->loadMissing('items.product');
        $missing = $order->items->map(function ($item) {
            $left = $item->activeQuantity() - (int) $item->allocations->where('status', '!=', OrderItemAllocation::CANCELLED)->sum('quantity');
            return $left > 0 ? ($item->product?->name ?? 'Məhsul').': '.$left.' ədəd' : null;
        })->filter();

        return 'Kuryer bütün məhsullar depolara seçiləndən sonra təyin olunur'
            .($missing->isNotEmpty() ? ' — seçilməyib: '.$missing->implode(', ') : '').'.';
    }

    /** @param  array<int, bool>|null  $courierPays  depo id => kuryer depoya pul verirmi (null — dəyişmir) */
    public function assignCourier(Order $order, User $courier, int $actor, ?array $courierPays = null): void
    {
        abort_unless(FinanceService::isCourier($courier) && $courier->active, 422, 'Seçilən əməkdaş aktiv kuryer deyil.');
        $changed = DB::transaction(function () use ($order, $courier, $actor, $courierPays) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if (($block = $this->courierBlock($locked)) !== null) {
                throw ValidationException::withMessages(['courier' => $block]);
            }
            $changed = (int) $locked->courier_id !== (int) $courier->id;
            $locked->forceFill(['courier_id' => $courier->id])->save();
            $note = 'Kuryer: '.trim($courier->full_name);
            if (!$this->set($locked, 'courier_assigned', $actor, $note) && $changed) {
                // Status eynidir, kuryer dəyişdi — tarixçədə görünsün
                DB::table('order_status_logs')->insert([
                    'order_id' => $locked->id, 'status_id' => $locked->order_status_id, 'user_id' => $actor,
                    'note' => 'Kuryer dəyişdirildi → '.trim($courier->full_name), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            app(FinanceService::class)->courierAccount($courier);
            // Götürülməmiş hissələr üçün "pul ver / pul vermə" (götürülmüşün ödənişi artıq həll olunub)
            foreach ($courierPays ?? [] as $warehouseId => $pays) {
                OrderItemAllocation::whereIn('order_item_id', $locked->items()->select('id'))->where('warehouse_id', (int) $warehouseId)
                    ->whereNotIn('status', [OrderItemAllocation::PICKED, ...OrderItemAllocation::SUPPLY_INACTIVE])
                    ->update(['courier_pays' => (bool) $pays]);
            }

            return $changed;
        });
        if ($changed) {
            $this->notifyCourier($order, $courier, null, $actor);
        }
    }

    /** Kuryerə Telegram və tətbiq bildirişi + push (CourierNotifier): sifariş ona təhkim olundu (xəta əməliyyatı pozmur) */
    private function notifyCourier(Order $order, User $courier, ?User $from, int $actor): void
    {
        rescue(fn () => app(\App\Services\Telegram\CourierTelegram::class)->orderAssigned($order->fresh(), $courier, $from, $actor), null, !app()->runningUnitTests());
        rescue(fn () => app(\App\Services\Courier\CourierNotifier::class)->orderAssigned($order->fresh(), $courier, $from), null, !app()->runningUnitTests());
    }

    /*
     |--------------------------------------------------------------------------
     | Kuryer: çatdırılma
     |--------------------------------------------------------------------------
     */

    /** Müştəridən alınacaq nağd: qapıda ödənişdə yekun, qalanlarında 0 (onlayn, bonus, kredit) */
    public function collectAmount(Order $order): float
    {
        return $order->payment_status === 'cod' ? round((float) $order->total, 2) : 0.0;
    }

    /** "Çatdırılmaya başladım"a mane olan səbəb: bütün miqdar götürülməlidir, problem olmamalıdır */
    public function deliveryBlock(Order $order): ?string
    {
        $order->loadMissing(['status', 'items.allocations', 'items.product']);
        if ($order->status?->code !== 'courier_assigned') {
            return null; // artıq yoldadır və ya hələ kuryer mərhələsi deyil
        }
        foreach ($order->items as $item) {
            $parts = $item->allocations->where('status', '!=', OrderItemAllocation::CANCELLED);
            $name = $item->product?->name ?? 'Məhsul';
            if ($parts->contains('status', OrderItemAllocation::PROBLEM)) {
                return $name.': problem həll olunmayıb.';
            }
            if ((int) $parts->where('status', OrderItemAllocation::PICKED)->sum('quantity') < $item->activeQuantity()) {
                return $name.': hələ götürülməyib.';
            }
        }

        return null;
    }

    public function startDelivery(Order $order, int $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $locked = $this->courierOrder($order, $actor);
            if ($locked->status?->code === 'sent') return; // təkrar klik
            $this->ensure($locked->status?->code === 'courier_assigned', 'Bu mərhələdə çatdırılmaya başlamaq olmaz.');
            $this->ensure(($block = $this->deliveryBlock($locked)) === null, (string) $block);

            $this->set($locked, 'sent', $actor);
            app(\App\Services\Admin\AdminNotifier::class)->courier('courier_sent', $locked, $actor); // admin paneldə zəng
        });
    }


    /**
     * "Təhvil verdim": qapıda ödənişdə kuryer aldığı məbləği təsdiqləyir → Müştəri → Kuryer (qalığı artır),
     * ödəniş "ödənilib" olur. Məbləğ fərqlidirsə — təhvil yazılmır, "Problem" bildirilməlidir.
     */
    public function deliver(Order $order, int $actor, ?float $collected): void
    {
        DB::transaction(function () use ($order, $actor, $collected) {
            $locked = $this->courierOrder($order, $actor);
            if ($locked->status?->code === 'delivered') return;
            $this->ensure($locked->status?->code === 'sent', 'Əvvəlcə "Çatdırılmaya başladım" seçin.');

            $amount = $this->collectAmount($locked);
            if ($amount > 0) {
                $this->ensure($collected !== null && (int) round($collected * 100) === (int) round($amount * 100),
                    'Alınacaq məbləğ dəyişib — indi '.number_format($amount, 2).' AZN. Səhifəni yeniləyin; fərq varsa "Problem" bildirin.');
                $finance = app(FinanceService::class);
                $finance->record($finance->system('customer'), $finance->courierAccount(User::findOrFail($actor)), $amount, 'customer_payment', [
                    'order_id' => $locked->id, 'note' => 'Qapıda nağd ödəniş',
                ], $actor);
                $locked->forceFill(['payment_status' => 'paid'])->save();
            }
            $this->set($locked, 'delivered', $actor);
            app(\App\Services\Admin\AdminNotifier::class)->courier('courier_delivered', $locked, $actor);
        });
    }

    /** Çatdırılma problemi (müştəri yoxdur, qəbul etmir...): status dəyişmir, tarixçəyə yazılır */
    public function deliveryProblem(Order $order, int $actor, string $note): void
    {
        DB::transaction(function () use ($order, $actor, $note) {
            $locked = $this->courierOrder($order, $actor);
            DB::table('order_status_logs')->insert([
                'order_id' => $locked->id, 'status_id' => $locked->order_status_id, 'user_id' => $actor, 'kind' => 'delivery_problem',
                'note' => 'Çatdırılma problemi: '.$note, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Aktiv kuryerlər, baş kuryer daxil (rol yoxdursa — boş) */
    public function couriers()
    {
        return FinanceService::courierUsers()->where('active', 1)->orderBy('name')->get();
    }

    /** Kuryerin sifarişi başqa kuryerə ötürməsinə mane olan səbəb (yoxdursa null) */
    public function transferBlock(Order $order): ?string
    {
        $order->loadMissing(['status', 'items.allocations']);

        return match (true) {
            $order->status?->code !== 'courier_assigned' => 'Yola çıxandan sonra sifariş ötürülmür.',
            $order->items->flatMap->allocations->contains('status', OrderItemAllocation::PICKED)
                => 'Məhsul artıq götürülüb — ötürmək olmaz. Operatorla əlaqə saxlayın.',
            default => null,
        };
    }

    /**
     * Sifarişi başqa kuryerə ötürmək — toplamadan əvvəl (məs. depolar ona uzaqdır).
     * Kuryer öz sifarişini ötürür; $byHead — baş kuryer (courier.team) istənilən kuryerin sifarişini ötürür, köhnə kuryerə də bildiriş gedir.
     */
    public function transferCourier(Order $order, User $to, int $actor, bool $byHead = false): void
    {
        [$from, $head] = DB::transaction(function () use ($order, $to, $actor, $byHead) {
            $locked = $byHead
                ? Order::with(['status', 'items.allocations', 'items.product'])->lockForUpdate()->findOrFail($order->id)
                : $this->courierOrder($order, $actor);
            $this->ensure($locked->courier_id !== null, 'Sifarişə kuryer təyin olunmayıb.');
            $this->ensure(($block = $this->transferBlock($locked)) === null, (string) $block);
            $this->ensure($to->id !== (int) $locked->courier_id, 'Başqa kuryer seçin.');
            $this->ensure($to->active && FinanceService::isCourier($to), 'Seçilən əməkdaş aktiv kuryer deyil.');
            $from = User::find($locked->courier_id);
            $head = $byHead ? User::find($actor) : null;
            $locked->forceFill(['courier_id' => $to->id])->save();
            DB::table('order_status_logs')->insert([
                'order_id' => $locked->id, 'status_id' => $locked->order_status_id, 'user_id' => $actor, 'kind' => 'courier_change',
                'note' => ($head ? 'Baş kuryer ('.trim((string) $head->full_name).') sifarişi ötürdü: ' : 'Kuryer sifarişi ötürdü: ')
                    .trim((string) $from?->full_name).' → '.trim((string) $to->full_name),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(FinanceService::class)->courierAccount($to);

            return [$from, $head];
        });
        rescue(fn () => app(\App\Services\Telegram\CourierTelegram::class)->orderAssigned($order->fresh(), $to, $from, $actor, $head), null, !app()->runningUnitTests());
        rescue(fn () => app(\App\Services\Courier\CourierNotifier::class)->orderAssigned($order->fresh(), $to, $from), null, !app()->runningUnitTests());
        if ($head && $from && $from->id !== $head->id) {
            rescue(fn () => app(\App\Services\Telegram\CourierTelegram::class)->orderTakenAway($order->fresh(), $from, $to, $head), null, !app()->runningUnitTests());
            rescue(fn () => app(\App\Services\Courier\CourierNotifier::class)->orderTakenAway($order->fresh(), $from, $to, $head), null, !app()->runningUnitTests());
        }
    }

    /** Sifariş kilidlənir; yalnız ona təyin olunmuş kuryer */
    private function courierOrder(Order $order, int $actor): Order
    {
        $locked = Order::with(['status', 'items.allocations', 'items.product'])->lockForUpdate()->findOrFail($order->id);
        abort_unless((int) $locked->courier_id === $actor, 403, 'Bu sifariş sizə təyin olunmayıb.');

        return $locked;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}
