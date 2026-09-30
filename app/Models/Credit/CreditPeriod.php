<?php

namespace App\Models\Credit;

use Illuminate\Database\Eloquent\Model;

class CreditPeriod extends Model
{
    protected $fillable = ['month', 'interest_rate', 'active', 'sort_order'];

    protected $casts = [
        'month' => 'integer',
        'interest_rate' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Məbləğ bu həddə qədər (daxil) olanda yalnız SMALL_AMOUNT_MONTHS müddətləri təklif olunur */
    public const SMALL_AMOUNT_LIMIT = 200;

    public const SMALL_AMOUNT_MONTHS = [3, 6];

    public function availableFor(float $amount): bool
    {
        return round($amount, 2) > self::SMALL_AMOUNT_LIMIT || in_array($this->month, self::SMALL_AMOUNT_MONTHS, true);
    }

    /** JS üçün eyni qayda (layouts/app → appData.creditRule) */
    public static function amountRule(): array
    {
        return ['limit' => self::SMALL_AMOUNT_LIMIT, 'months' => self::SMALL_AMOUNT_MONTHS];
    }
}
