<?php

namespace App\Services\Referral;

use App\Models\Setting;

/**
 * "Dostunu dəvət et" (referal) proqramının admin ayarları (settings cədvəli, referal_* açarları).
 * Proqramın bütün məntiqi (link, qeydiyyat, bonus yazılması) ayarları yalnız bu sinif vasitəsilə oxuyur.
 *
 * Bonus dəvət olunanın ilk sifarişi kuryer tərəfindən "Təhvil verildi" edildikdə yazılır.
 */
class ReferralSettings
{
    /** Dəvət olunanın bonusu: ilk sifarişdə endirim və ya bonus balansı */
    public const MODE_DISCOUNT = 'discount';
    public const MODE_BALANCE = 'balance';

    /** Limit dolanda: link bağlanır və ya dəvət işləyir, amma dəvət edən bonus almır */
    public const LIMIT_BLOCK = 'block';
    public const LIMIT_NO_REWARD = 'no_reward';

    public const LOCALES = ['az', 'en', 'ru'];

    public const OG_IMAGE_DIR = 'frontend/uploads/referral';

    /** Açar => standart dəyər (Setting::valueOf ilə oxunur) */
    public const DEFAULTS = [
        'referral_enabled' => 0,
        'referral_referrer_amount' => 17,
        'referral_invitee_amount' => 10,
        'referral_invitee_mode' => self::MODE_DISCOUNT,
        'referral_discount_with_promo' => 0,
        'referral_min_order_enabled' => 0,
        'referral_min_order_amount' => 60,
        'referral_installment_allowed' => 0,
        'referral_expiry_enabled' => 0,
        'referral_referrer_expiry_days' => 90,
        'referral_invitee_expiry_days' => 120,
        'referral_limit_enabled' => 0,
        'referral_limit_count' => 50,
        'referral_limit_mode' => self::LIMIT_NO_REWARD,
        'referral_inviter_requires_order' => 0,
        'referral_cookie_days' => 30,
        'referral_og_image' => '',
        'referral_share_text_az' => 'Parfumshop.az-da ilk sifarişinə :amount ₼ hədiyyə! Mənim linkimlə qeydiyyatdan keç:',
        'referral_share_text_en' => 'Get :amount ₼ off your first order at Parfumshop.az! Sign up with my link:',
        'referral_share_text_ru' => 'Получите :amount ₼ на первый заказ в Parfumshop.az! Зарегистрируйтесь по моей ссылке:',
    ];

    private array $values = [];

    public function __construct()
    {
        $stored = Setting::query()->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key');
        foreach (self::DEFAULTS as $key => $default) {
            $this->values[$key] = $stored[$key] ?? $default;
        }
    }

    public function raw(string $key): mixed
    {
        return $this->values[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public function enabled(): bool
    {
        return (bool) (int) $this->raw('referral_enabled');
    }

    public function referrerAmount(): float
    {
        return round(max(0, (float) $this->raw('referral_referrer_amount')), 2);
    }

    public function inviteeAmount(): float
    {
        return round(max(0, (float) $this->raw('referral_invitee_amount')), 2);
    }

    public function inviteeMode(): string
    {
        return $this->raw('referral_invitee_mode') === self::MODE_BALANCE ? self::MODE_BALANCE : self::MODE_DISCOUNT;
    }

    /** Endirim rejimində promo kodla birlikdə işləyirmi (balans rejimində mənasızdır) */
    public function discountCombinesWithPromo(): bool
    {
        return $this->inviteeMode() === self::MODE_DISCOUNT && (bool) (int) $this->raw('referral_discount_with_promo');
    }

    /** Minimum sifariş məbləği; null — şərt yoxdur */
    public function minOrderAmount(): ?float
    {
        return (int) $this->raw('referral_min_order_enabled') ? round(max(0, (float) $this->raw('referral_min_order_amount')), 2) : null;
    }

    public function installmentAllowed(): bool
    {
        return (bool) (int) $this->raw('referral_installment_allowed');
    }

    /** Bonusun istifadə müddəti (gün); null — müddətsiz */
    public function referrerExpiryDays(): ?int
    {
        return (int) $this->raw('referral_expiry_enabled') ? max(1, (int) $this->raw('referral_referrer_expiry_days')) : null;
    }

    public function inviteeExpiryDays(): ?int
    {
        return (int) $this->raw('referral_expiry_enabled') ? max(1, (int) $this->raw('referral_invitee_expiry_days')) : null;
    }

    /** Bir müştərinin dəvət limiti; null — limitsiz */
    public function inviteLimit(): ?int
    {
        return (int) $this->raw('referral_limit_enabled') ? max(1, (int) $this->raw('referral_limit_count')) : null;
    }

    public function limitMode(): string
    {
        return $this->raw('referral_limit_mode') === self::LIMIT_BLOCK ? self::LIMIT_BLOCK : self::LIMIT_NO_REWARD;
    }

    /** Dəvət etmək üçün ən azı bir təhvil alınmış sifariş tələb olunurmu */
    public function inviterRequiresOrder(): bool
    {
        return (bool) (int) $this->raw('referral_inviter_requires_order');
    }

    /** Dəvət linki açılandan sonra kodun yadda saxlanma müddəti (gün) */
    public function cookieDays(): int
    {
        return min(365, max(1, (int) $this->raw('referral_cookie_days')));
    }

    public function ogImageUrl(): ?string
    {
        $file = (string) $this->raw('referral_og_image');

        return $file !== '' ? asset(self::OG_IMAGE_DIR.'/'.$file) : null;
    }

    /** Paylaşma mətni (dəvət olunanın məbləği :amount yerinə qoyulur) */
    public function shareText(?string $locale = null): string
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'az';
        $text = (string) ($this->raw('referral_share_text_'.$locale) ?: self::DEFAULTS['referral_share_text_az']);

        return str_replace(':amount', rtrim(rtrim(number_format($this->inviteeAmount(), 2, '.', ''), '0'), '.'), $text);
    }
}
