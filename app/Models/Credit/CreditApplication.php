<?php

namespace App\Models\Credit;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;

class CreditApplication extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'credit_period_id',
        'interest_rate',
        'total',
        'standard_total',
        'total_edited_by',
        'monthly',
        'credit_status_id',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:2',
            'total'         => 'decimal:2',
            'standard_total' => 'decimal:2',
            'monthly'       => 'decimal:2',
        ];
    }

    /**
     * Razılaşdırılan ümumi məbləğ: operator göndərməyibsə standart; göndəribsə faizsiz məbləğ ($min) ilə standart arasında olmalıdır.
     * Aralıqdan kənardırsa null.
     */
    public static function agreedTotal(float $standard, float $min, ?float $requested): ?float
    {
        if ($requested === null) {
            return round($standard, 2);
        }
        $requested = round($requested, 2);

        return $requested >= round($min, 2) && $requested <= round($standard, 2) ? $requested : null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function status()
    {
        return $this->belongsTo(CreditStatus::class, 'credit_status_id');
    }

    public function period()
    {
        return $this->belongsTo(CreditPeriod::class, 'credit_period_id');
    }
}
