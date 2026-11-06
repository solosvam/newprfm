<?php

namespace App\Models\Credit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CreditPeriod extends Model
{
    protected $fillable = ['month', 'interest_rate', 'bank_fee_rate', 'min_amount', 'active', 'sort_order'];

    protected $casts = [
        'month' => 'integer',
        'interest_rate' => 'decimal:2',
        'bank_fee_rate' => 'decimal:2', // Ferrum-un ümumi məbləğdən çıxdığı faiz (bizə qalan: total × (1 − faiz / 100))
        'min_amount' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    private const RULE_CACHE = 'credit-periods:min-amounts';

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetRule());
        static::deleted(fn () => static::forgetRule());
    }

    public static function forgetRule(): void
    {
        Cache::forget(self::RULE_CACHE);
    }

    /**
     * Müddət yalnız bu müddətin FAİZLİ yekun məbləği min_amount-dan YUXARI olanda təklif olunur
     * (admin → Kredit → Faizlər; boş — məhdudiyyət yoxdur). Məs. 199 ₼ 9 ay 25%-lə 248.75 ₼ > 200 — göstərilir.
     * JS-də eyni qayda: main.js, checkout.js, credit-application.js (qiymət × (1 + faiz / 100) > min).
     */
    public function availableFor(float $amount): bool
    {
        return $this->min_amount === null
            || round($amount * (1 + (float) $this->interest_rate / 100), 2) > (float) $this->min_amount;
    }

    public function unavailableMessage(): string
    {
        return __('credit_period_not_available', [
            'months' => $this->month,
            'min' => rtrim(rtrim(number_format((float) $this->min_amount, 2, '.', ''), '0'), '.'),
        ]);
    }

    /** JS üçün eyni qayda (layouts/app → appData.creditRule): {ay: minimum məbləğ} */
    public static function amountRule(): array
    {
        // hər səhifədə (layouts/app) lazımdır — keşdə; admin dəyişəndə booted() təmizləyir
        return ['mins' => Cache::rememberForever(self::RULE_CACHE, fn () => static::where('active', 1)->whereNotNull('min_amount')
            ->pluck('min_amount', 'month')->map(fn ($v) => (float) $v)->all())];
    }
}
