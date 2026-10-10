<?php

namespace App\Services\Referral;

use App\Support\ShortUrl;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerReferral;
use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referal: dəvət kodu, linkin izlənməsi (cookie), qeydiyyatda bağlanma.
 * İlk sifariş endirimi (checkout) və bonusların yazılması (ilk sifariş "Təhvil verildi").
 */
class ReferralService
{
    public const COOKIE = 'referral_code';

    /** Qarışdırıla bilən simvollar (0/O, 1/I/L) çıxarılıb */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 8;

    public function __construct(private ReferralSettings $settings)
    {
    }

    public function settings(): ReferralSettings
    {
        return $this->settings;
    }

    /** Müştərinin dəvət kodu — yoxdursa yaradılır */
    public function codeFor(Customer $customer): string
    {
        if ($customer->referral_code) {
            return $customer->referral_code;
        }

        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Customer::where('referral_code', $code)->exists());

        $customer->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function linkFor(Customer $customer): string
    {
        // Müştərinin paylaşdığı link qısa domenlədir (paf.az) — App\Support\ShortUrl
        return ShortUrl::route('referral.track', $this->codeFor($customer));
    }

    public function normalize(?string $code): ?string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));

        return $code !== '' ? Str::limit($code, 12, '') : null;
    }

    public function findReferrer(?string $code): ?Customer
    {
        $code = $this->normalize($code);

        return $code ? Customer::where('referral_code', $code)->where('active', true)->first() : null;
    }

    /** Dəvət etmək üçün ən azı bir təhvil alınmış sifariş şərti ödənilibmi */
    public function meetsOrderRequirement(Customer $customer): bool
    {
        if (!$this->settings->inviterRequiresOrder()) {
            return true;
        }

        return $customer->orders()->whereHas('status', fn ($q) => $q->where('code', 'delivered'))->exists();
    }

    public function invitedCount(Customer $customer): int
    {
        return $customer->referrals()->count();
    }

    public function limitReached(Customer $customer): bool
    {
        $limit = $this->settings->inviteLimit();

        return $limit !== null && $this->invitedCount($customer) >= $limit;
    }

    /**
     * Müştəri yeni dəvət qəbul edə bilərmi.
     * null — bəli; əks halda səbəb: 'disabled' | 'requires_order' | 'limit'
     */
    public function blockReason(Customer $customer): ?string
    {
        if (!$this->settings->enabled()) {
            return 'disabled';
        }
        if (!$this->meetsOrderRequirement($customer)) {
            return 'requires_order';
        }
        if ($this->limitReached($customer) && $this->settings->limitMode() === ReferralSettings::LIMIT_BLOCK) {
            return 'limit';
        }

        return null;
    }

    /** Qeydiyyat zamanı dəvət olunanı dəvət edənə bağlayır. Kod etibarsızdırsa false. */
    public function attach(Customer $invitee, ?string $code): bool
    {
        $referrer = $this->findReferrer($code);
        if (!$referrer || $referrer->id === $invitee->id || $this->blockReason($referrer) !== null) {
            return false;
        }

        return DB::transaction(function () use ($referrer, $invitee) {
            if (CustomerReferral::where('invitee_id', $invitee->id)->lockForUpdate()->exists()) {
                return false;
            }

            CustomerReferral::create([
                'referrer_id' => $referrer->id,
                'invitee_id' => $invitee->id,
                'status' => CustomerReferral::STATUS_REGISTERED,
                // no_reward rejimində limit dolubsa dəvət işləyir, amma dəvət edən bonus almır
                'referrer_rewardable' => !$this->limitReached($referrer),
            ]);

            return true;
        });
    }

    /** Qeydiyyat formu üçün kodun yoxlanması (attach-dan əvvəl) */
    public function isUsableCode(?string $code): bool
    {
        $referrer = $this->findReferrer($code);

        return $referrer !== null && $this->blockReason($referrer) === null;
    }

    /** "Hissə-hissə ödənişli ilk sifariş də sayılsın" — yalnız öz kreditimiz; Birbank taksit kart ödənişi kimi sayılır */
    public const INSTALLMENT_METHODS = ['installment'];

    /**
     * Dəvət olunanın ilk sifariş endirimi (yalnız saytda — səbət və checkout; CRM sifarişlərinə tətbiq olunmur).
     * Şərtlər: proqram aktiv, rejim "endirim", müştəri dəvətlə gəlib (bonus hələ yazılmayıb), ləğv olunmamış sifarişi yoxdur.
     * Səbət/checkout JS-i üçün: məbləğ və sifarişə bağlı şərtlər (minimum, promo, hissə-hissə); null — endirim yoxdur.
     *
     * @return array{amount: float, min: ?float, with_promo: bool, installment: bool}|null
     */
    public function discountOffer(?Customer $customer): ?array
    {
        if (!$customer || !$this->settings->enabled() || $this->settings->inviteeMode() !== ReferralSettings::MODE_DISCOUNT) {
            return null;
        }
        $amount = $this->settings->inviteeAmount();
        if ($amount <= 0) {
            return null;
        }
        $pending = CustomerReferral::where('invitee_id', $customer->id)->where('status', CustomerReferral::STATUS_REGISTERED)->exists();
        if (!$pending || $customer->orders()->whereDoesntHave('status', fn ($q) => $q->where('code', 'cancelled'))->exists()) {
            return null;
        }

        return [
            'amount' => $amount,
            'min' => $this->settings->minOrderAmount(),
            'with_promo' => $this->settings->discountCombinesWithPromo(),
            'installment' => $this->settings->installmentAllowed(),
        ];
    }

    /** Checkout-da sifarişə tətbiq olunan referal endirimi (promo endirimindən sonra qalan məbləğdən çox deyil) */
    public function discountFor(Customer $customer, float $subtotal, float $promoDiscount, string $paymentCode): float
    {
        $offer = $this->discountOffer($customer);
        if (!$offer
            || ($offer['min'] !== null && $subtotal < $offer['min'])
            || ($promoDiscount > 0 && !$offer['with_promo'])
            || (!$offer['installment'] && in_array($paymentCode, self::INSTALLMENT_METHODS, true))) {
            return 0.0;
        }

        return round(min($offer['amount'], max(0, $subtotal - $promoDiscount)), 2);
    }

    /**
     * Sifariş "Təhvil verildi" olanda (OrderStatusService::set): dəvət olunanın ilk uyğun sifarişidirsə bonuslar yazılır.
     *  - dəvət edənə — "Dəvət edənin bonusu" (limit dolubsa, no_reward rejimində yazılmır);
     *  - dəvət olunana — "Bonus balansına" rejimində bonus; "endirim" rejimində o, endirimi checkout-da alıb.
     * Sifariş şərtlərə uyğun deyilsə (minimum məbləğ, hissə-hissə ödəniş) dəvət gözləməkdə qalır — növbəti sifariş sayıla bilər.
     * Status dəyişməsi ilə eyni tranzaksiyada işləyir.
     */
    public function rewardForDeliveredOrder(Order $order): void
    {
        if (!$this->settings->enabled() || !$order->customer_id) {
            return;
        }
        $referral = CustomerReferral::where('invitee_id', $order->customer_id)
            ->where('status', CustomerReferral::STATUS_REGISTERED)->lockForUpdate()->first();
        if (!$referral || !$this->orderQualifies($order)) {
            return;
        }

        $referrer = Customer::whereKey($referral->referrer_id)->where('active', true)->first();
        $referrerAmount = $referral->referrer_rewardable && $referrer ? $this->settings->referrerAmount() : 0.0;
        if ($referrerAmount > 0) {
            // qeyddə qarşı tərəfin adı (sifariş nömrəsi order_id ilə ayrıca göstərilir)
            $this->credit($referrer, $referrerAmount, $order, $this->settings->referrerExpiryDays(), $order->customer?->full_name);
        }

        $isBalance = $this->settings->inviteeMode() === ReferralSettings::MODE_BALANCE;
        $inviteeAmount = $isBalance ? $this->settings->inviteeAmount() : (float) $order->referral_discount;
        if ($isBalance && $inviteeAmount > 0) {
            $this->credit($order->customer, $inviteeAmount, $order, $this->settings->inviteeExpiryDays(),
                Customer::whereKey($referral->referrer_id)->first()?->full_name);
        }

        $referral->update([
            'status' => CustomerReferral::STATUS_REWARDED,
            'order_id' => $order->id,
            'referrer_amount' => $referrerAmount,
            'invitee_amount' => $inviteeAmount,
            'rewarded_at' => now(),
        ]);
    }

    /** Referal endirimi alınıbsa sifariş checkout-da şərtlərdən keçib; əks halda minimum məbləğ və hissə-hissə yoxlanır */
    private function orderQualifies(Order $order): bool
    {
        if ((float) $order->referral_discount > 0) {
            return true;
        }
        $min = $this->settings->minOrderAmount();
        if ($min !== null && (float) $order->subtotal < $min) {
            return false;
        }

        return $this->settings->installmentAllowed()
            || !in_array($order->paymentMethod?->code, self::INSTALLMENT_METHODS, true);
    }

    private function credit(Customer $customer, float $amount, Order $order, ?int $expiryDays, ?string $otherName): void
    {
        $otherName = trim((string) $otherName);
        DB::table('customers')->where('id', $customer->id)->increment('bonus_balance', $amount);
        $customer->bonusTransactions()->create([
            'order_id' => $order->id,
            'type' => 'referral',
            'amount' => $amount,
            'note' => 'Dəvət bonusu'.($otherName !== '' ? ' ('.$otherName.')' : ''),
            'expires_at' => $expiryDays ? now()->addDays($expiryDays) : null,
        ]);
    }
}
