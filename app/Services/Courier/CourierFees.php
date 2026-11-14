<?php

namespace App\Services\Courier;

use App\Models\Finance\FinanceAccount;
use App\Models\Finance\MoneyMovement;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Əlavə çatdırılma (orders.courier_fee) — uzaq ünvan üçün operatorun yazdığı məbləğ (5, 10 AZN...).
 *  - Müştərinin yekun ödənişinə daxildir (orders.total), amma şirkətin satışı / gəliri deyil.
 *  - Tam məbləğ sifarişi təhvil verən kuryerin qazancıdır (orders.courier_id):
 *      qapıda ödənişdə kuryer onu müştəridən alıb özündə saxlayır (təhvil verəcəyi pula düşmür);
 *      kartla / hissə-hissə / bonusla ödənilibsə pul şirkətdədir — kuryerin balansı mənfi olur (şirkət ona borcludur).
 *  - Maliyyədə: courier_fee_in (müştəri → pulun düşdüyü hesab) və təhvildə courier_earning (kuryer → Kənar),
 *    kateqoriya "Əlavə çatdırılma" — gəlir / xərc hesabatlarına düşmür (MoneyMovement::TRANSFER_KINDS).
 */
class CourierFees
{
    /** Operatorun yaza biləcəyi ən böyük məbləğ (çoxu validasiyada rədd olunur — səssizcə kəsilmir) */
    public const MAX = 50;

    /** Sürətli seçim düymələri */
    public const PRESETS = [5, 10];

    public function __construct(private FinanceService $finance) {}

    /** Sifarişin əlavə çatdırılmasını dəyişməyə mane olan səbəb (yoxdursa null) */
    public function changeBlock(Order $order): ?string
    {
        $order->loadMissing(['status', 'paymentMethod']);
        $code = $order->paymentMethod?->code;

        return match (true) {
            in_array($order->status?->code, ['delivered', 'cancelled'], true) => 'Sifariş bağlanıb — əlavə çatdırılma dəyişmir.',
            $code === 'installment' => 'Hissə-hissə ödənişdə əlavə çatdırılma yalnız sifariş yaradılanda yazılır (kredit məbləğinə daxildir).',
            $code === 'bonus_balance' => 'Bonusla ödənilmiş sifarişin məbləği dəyişmir.',
            $order->payment_status === 'paid' => 'Sifariş ödənilib — məbləğ dəyişmir.',
            $order->hasPendingPayment() => 'Bankda nəticəsi bəlli olmayan ödəniş var — əvvəlcə "Bankdan yoxla".',
            MoneyMovement::where('order_id', $order->id)->where('kind', 'customer_payment')->exists() => 'Müştərinin ödənişi artıq yazılıb.',
            default => null,
        };
    }

    /** Əlavə çatdırılmanı dəyişir: yekun məbləğ fərq qədər artır / azalır; sifariş tarixçəsinə yazılır (kind = courier_fee) */
    public function change(Order $order, float $fee, ?int $actor = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $fee, $actor) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($block = $this->changeBlock($locked)) {
                throw ValidationException::withMessages(['courier_fee' => $block]);
            }
            $fee = self::normalize($fee);
            if ($fee > self::MAX) {
                throw ValidationException::withMessages(['courier_fee' => 'Çatdırılma haqqı '.number_format(self::MAX).' AZN-dən çox ola bilməz.']);
            }
            $old = round((float) $locked->courier_fee, 2);
            $diff = round($fee - $old, 2);
            if (abs($diff) < 0.005) {
                return;
            }
            $locked->forceFill(['courier_fee' => $fee, 'total' => max(0, round((float) $locked->total + $diff, 2))])->save();
            \Illuminate\Support\Facades\DB::table('order_status_logs')->insert([
                'order_id' => $locked->id, 'status_id' => $locked->order_status_id, 'user_id' => $actor, 'kind' => 'courier_fee',
                'note' => 'Çatdırılma haqqı: '.number_format($old, 2).' → '.number_format($fee, 2).' AZN (yekun '.number_format((float) $locked->total, 2).' AZN)',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public static function normalize(mixed $fee): float
    {
        return round(max(0, (float) $fee), 2);
    }

    /**
     * Müştəridən gələn əlavə çatdırılma — pulun düşdüyü hesaba (bir dəfə). Qaytarılan: yazılan məbləğ
     * (satışdan — customer_payment-dən — çıxılmalıdır).
     */
    public function recordIncoming(Order $order, FinanceAccount $to, float $max, ?int $actor = null): float
    {
        $fee = min(round((float) $order->courier_fee, 2), round($max, 2));
        if ($fee <= 0 || MoneyMovement::where('kind', 'courier_fee_in')->where('order_id', $order->id)->exists()) {
            return 0.0;
        }
        $this->finance->record($this->finance->system('customer'), $to, $fee, 'courier_fee_in', [
            'order_id' => $order->id, 'note' => 'Əlavə çatdırılma — kuryerin qazancı',
        ], $actor);

        return $fee;
    }

    /** Təhvildə: əlavə çatdırılma sifarişi çatdıran kuryerin qazancı (bir dəfə) */
    public function recordEarning(Order $order, ?int $actor = null): void
    {
        $fee = round((float) $order->courier_fee, 2);
        if ($fee <= 0 || !$order->courier_id || MoneyMovement::where('kind', 'courier_earning')->where('order_id', $order->id)->exists()) {
            return;
        }
        $courier = User::find($order->courier_id);
        if (!$courier) {
            return;
        }
        $this->finance->record($this->finance->courierAccount($courier), $this->finance->system('external'), $fee, 'courier_earning', [
            'order_id' => $order->id, 'note' => 'Əlavə çatdırılma qazancı · '.$order->order_no,
        ], $actor);
    }

    /**
     * Kuryerlərin əlavə qazancı günlər üzrə (təhvil günü). $courierIds null — hamısı.
     *
     * @return Collection<int, array{date: Carbon, courier: ?User, total: float, orders: Collection}>  yeni gün yuxarıda
     */
    public function daily(?array $courierIds, Carbon $from, Carbon $to): Collection
    {
        $accounts = FinanceAccount::where('type', 'courier')
            ->when($courierIds !== null, fn ($q) => $q->whereIn('user_id', $courierIds))->get()->keyBy('id');
        if ($accounts->isEmpty()) {
            return collect();
        }
        $rows = MoneyMovement::with('order:id,order_no,customer_id,courier_fee,payment_method_id', 'order.paymentMethod:id,code,name_az')
            ->where('kind', 'courier_earning')->whereIn('from_account_id', $accounts->keys())
            ->whereDoesntHave('reversedBy')
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('occurred_at')->orderByDesc('id')->get();
        $users = User::whereIn('id', $accounts->pluck('user_id'))->get()->keyBy('id');

        return $rows->groupBy(fn ($m) => $m->occurred_at->toDateString().'|'.$m->from_account_id)
            ->map(function ($group) use ($accounts, $users) {
                $first = $group->first();

                return [
                    'date' => $first->occurred_at->copy()->startOfDay(),
                    'courier' => $users[$accounts[$first->from_account_id]->user_id] ?? null,
                    'total' => round($group->sum(fn ($m) => (float) $m->amount), 2),
                    'orders' => $group->values(),
                ];
            })->values();
    }

    /** Bu gün hər kuryerin əlavə qazancı: user_id => AZN */
    public function todayByCourier(): Collection
    {
        return $this->daily(null, today(), today())->mapWithKeys(fn ($row) => [$row['courier']?->id => $row['total']])->filter();
    }
}
