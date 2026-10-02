<?php

namespace App\Services\Referral;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerReferral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referal: dəvət kodu, linkin izlənməsi (cookie), qeydiyyatda bağlanma.
 * Bonusun yazılması (ilk sifariş "Təhvil verildi") ayrıca addımdır.
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
        return route('referral.track', $this->codeFor($customer));
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
}
