<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Saytın əlaqə məlumatları (Admin → Ayarlar → Əlaqə məlumatları): footer, Əlaqə səhifəsi.
 * Bir sorğu ilə oxunur; boş saxlanan sahə saytda göstərilmir.
 */
class ContactInfo
{
    public const LOCALES = ['az', 'en', 'ru'];

    /** Açar => standart dəyər (əvvəl footer-də sabit yazılan dəyərlər) */
    public const DEFAULTS = [
        'contact_phone' => '(012) 310 22 55',
        'contact_whatsapp' => '(055) 551 07 00',
        'contact_email' => 'info@parfumshop.az',
        'contact_hours_az' => '09:00–19:00, B.e.–Ş. (bazar günü istirahət)',
        'contact_hours_en' => '09:00–19:00, Mon–Sat (Sunday off)',
        'contact_hours_ru' => '09:00–19:00, Пн–Сб (воскресенье выходной)',
        'contact_address_az' => '',
        'contact_address_en' => '',
        'contact_address_ru' => '',
        // köhnə saytın (www.parfumshop.az) footer-indən
        'contact_instagram' => 'https://www.instagram.com/parfumshop.az/',
        'contact_facebook' => 'https://www.facebook.com/ParfumShopAZ',
        'contact_youtube' => 'https://www.youtube.com/@parfumshop',
    ];

    private ?array $values = null;

    public function raw(string $key): string
    {
        if ($this->values === null) {
            $stored = Setting::query()->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key');
            $this->values = [];
            foreach (self::DEFAULTS as $k => $default) {
                $this->values[$k] = trim((string) ($stored[$k] ?? $default));
            }
        }

        return $this->values[$key] ?? '';
    }

    /** Dilə görə (iş saatları, ünvan); boşdursa Azərbaycan dili */
    public function localized(string $field, ?string $locale = null): string
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : app()->getLocale();

        return $this->raw("contact_{$field}_{$locale}") ?: $this->raw("contact_{$field}_az");
    }

    /** "(055) 551 07 00" → "+994555510700" */
    public static function e164(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '994')) {
            return '+'.$digits;
        }

        return '+994'.ltrim($digits, '0');
    }

    public function phoneUrl(): ?string
    {
        return ($tel = self::e164($this->raw('contact_phone'))) ? 'tel:'.$tel : null;
    }

    public function whatsappUrl(): ?string
    {
        return ($tel = self::e164($this->raw('contact_whatsapp'))) ? 'https://wa.me/'.ltrim($tel, '+') : null;
    }

    /** @return array<string, string> şəbəkə => link (yalnız doldurulanlar) */
    public function socials(): array
    {
        return array_filter([
            'instagram' => $this->raw('contact_instagram'),
            'facebook' => $this->raw('contact_facebook'),
            'youtube' => $this->raw('contact_youtube'),
        ]);
    }
}
