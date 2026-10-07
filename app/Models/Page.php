<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Məlumat səhifəsi. Açar (key) → sabit ünvan və route (front.page.{key}); mətn admindən 3 dildə.
 * HTML admin redaktorundan (Quill) gəlir — çıxışda clean() ilə yalnız icazəli teqlər saxlanılır.
 */
class Page extends Model
{
    /** key => [ünvan, admin adı]; ünvanlar ingiliscə (bir ünvan 3 dilə xidmət edir, /brands, /cart kimi). Dəyişərsə köhnəsinə redirect lazımdır */
    public const PAGES = [
        'delivery' => ['delivery', 'Çatdırılma və ödəniş'],
        'returns' => ['returns', 'Qaytarma və dəyişdirmə'],
        'about' => ['about', 'Haqqımızda'],
        'contact' => ['contact', 'Əlaqə'],
        'terms' => ['terms', 'İstifadə şərtləri'],
        'privacy' => ['privacy', 'Məxfilik siyasəti'],
    ];

    public const LOCALES = ['az', 'en', 'ru'];

    private const ALLOWED_TAGS = '<p><br><h2><h3><strong><b><em><i><u><s><ul><ol><li><a><blockquote>';

    protected $guarded = ['id'];

    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    public function url(): string
    {
        return route('front.page.'.$this->key);
    }

    /** Dilə görə sahə; boşdursa Azərbaycan dili */
    public function text(string $field, ?string $locale = null): ?string
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : app()->getLocale();

        return $this->{$field.'_'.$locale} ?: $this->{$field.'_az'};
    }

    /**
     * Redaktordan gələn HTML: icazəli teqlər, atributlardan yalnız a[href] (http/https/mailto/tel və ya /yol).
     */
    public static function clean(?string $html): string
    {
        $html = strip_tags((string) $html, self::ALLOWED_TAGS);

        return preg_replace_callback('/<(\/?)([a-z0-9]+)([^>]*)>/i', function ($m) {
            [$all, $slash, $tag, $attrs] = $m;
            $tag = strtolower($tag);
            if ($slash || $tag !== 'a') {
                return '<'.$slash.$tag.'>';
            }
            if (preg_match('/href\s*=\s*"([^"]*)"|href\s*=\s*\'([^\']*)\'/i', $attrs, $h)) {
                $href = html_entity_decode($h[1] !== '' ? $h[1] : ($h[2] ?? ''));
                if (preg_match('#^(https?://|mailto:|tel:|/)#i', $href) && !str_starts_with($href, '//')) {
                    $external = (bool) preg_match('#^https?://#i', $href) && !str_contains($href, parse_url(config('app.url'), PHP_URL_HOST) ?: '###');

                    return '<a href="'.e($href).'"'.($external ? ' target="_blank" rel="noopener"' : '').'>';
                }
            }

            return '<a>';
        }, $html);
    }
}
