<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Sayt → Popup-lar. Saytda bir səhifədə ən çox bir popup göstərilir (sort_order üzrə ilk uyğun gələn).
 * "Bir daha göstərmə": qonaqda localStorage, daxil olmuş müştəridə həm localStorage, həm popup_dismissals (cihazlar arası).
 * Statistika — popup_stats-da gün üzrə sayğaclar (adbaad log yoxdur).
 */
class Popup extends Model
{
    public const POSITIONS = ['center' => 'Mərkəzdə (pəncərə)', 'corner' => 'Aşağı küncdə (kart)', 'bar' => 'Aşağıda zolaq'];
    public const AUDIENCES = ['all' => 'Hamıya', 'guests' => 'Yalnız qonaqlara', 'customers' => 'Yalnız müştərilərə (daxil olmuş)'];
    /** "Harada": all — bütün səhifələr, yoxsa bunlardan bir neçəsi (pages sütununda vergüllə) */
    public const PAGES = ['home' => 'Ana səhifə', 'cart' => 'Səbət', 'checkout' => 'Checkout', 'success' => 'Uğurlu sifariş'];

    /** Səhifə → route adları */
    public const PAGE_ROUTES = [
        'home' => ['home'],
        'cart' => ['cart'],
        'checkout' => ['checkout'],
        'success' => ['checkout.success', 'one-click.success'],
    ];
    public const FREQUENCIES = ['once' => 'Bir dəfə', 'session' => 'Hər girişdə (hər sessiya)', 'daily' => 'Gündə bir dəfə'];
    public const EVENTS = ['shown' => 'shown', 'click' => 'clicked', 'close' => 'closed', 'dismiss' => 'dismissed'];
    public const LOCALES = ['az', 'en', 'ru'];

    private const CACHE = 'popups:live';

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
        'delay_seconds' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE));
        static::deleted(fn () => Cache::forget(self::CACHE));
    }

    public function stats()
    {
        return $this->hasMany(PopupStat::class);
    }

    public function scopeLive($query)
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** active | scheduled | ended | off */
    public function status(): string
    {
        return match (true) {
            !$this->active => 'off',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at && $this->ends_at->isPast() => 'ended',
            default => 'active',
        };
    }

    /** Dilə görə mətn; boşdursa Azərbaycan dili */
    public function text(string $field, ?string $locale = null): ?string
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : app()->getLocale();

        return $this->{$field.'_'.$locale} ?: $this->{$field.'_az'};
    }

    /** @return string[] seçilmiş səhifələr; boş — bütün səhifələr */
    public function pageList(): array
    {
        return $this->pages === 'all' || !$this->pages ? [] : array_values(array_intersect(explode(',', $this->pages), array_keys(self::PAGES)));
    }

    public function pagesLabel(): string
    {
        $pages = $this->pageList();

        return $pages ? implode(', ', array_map(fn ($p) => self::PAGES[$p], $pages)) : 'Bütün səhifələr';
    }

    /** Cari sorğunun səhifəsi (PAGES açarı) və ya null */
    public static function currentPage(): ?string
    {
        foreach (self::PAGE_ROUTES as $page => $routes) {
            if (request()->routeIs(...$routes)) {
                return $page;
            }
        }

        return null;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('frontend/uploads/popups/'.$this->image) : null;
    }

    /**
     * Ziyarətçiyə uyğun popup-lar (sort_order üzrə): auditoriya, səhifə, müştərinin gizlətdikləri çıxılır.
     * Tezlik və qonağın "bir daha göstərmə"si brauzerdə (popup.js) yoxlanılır.
     *
     * @return Collection<int, self>
     */
    public static function forVisitor(?int $customerId, ?string $page): Collection
    {
        // keş qısa: başlama/bitmə vaxtı dəqiqəlik dəqiqliklə işləsin
        $live = Cache::remember(self::CACHE, 60, fn () => static::live()->orderBy('sort_order')->orderByDesc('id')->get());
        if ($live->isEmpty()) {
            return $live;
        }
        $hidden = $customerId
            ? DB::table('popup_dismissals')->where('customer_id', $customerId)->pluck('popup_id')->all()
            : [];

        return $live->filter(fn (self $p) => (!$p->pageList() || in_array($page, $p->pageList(), true))
                && ($p->audience === 'all' || ($p->audience === 'customers') === (bool) $customerId)
                && !in_array($p->id, $hidden, true)
                // keşdəki siyahı 60 san köhnə ola bilər — dövrü burada da yoxla
                && (!$p->starts_at || $p->starts_at->lte(now())) && (!$p->ends_at || $p->ends_at->gt(now())))
            ->values();
    }

    /** popup.js üçün məlumat */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->text('title'),
            'body' => $this->text('body'),
            'button' => $this->text('button'),
            'url' => $this->link_url,
            'image' => $this->imageUrl(),
            'desktop' => $this->position_desktop,
            'mobile' => $this->position_mobile,
            'delay' => $this->delay_seconds,
            'frequency' => $this->frequency,
        ];
    }
}
