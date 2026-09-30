<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    protected $guarded = [];

    /** Daxili mərhələlər — müştəri "Hazırlanır" görür */
    public const CUSTOMER_MAP = [
        'confirmed' => 'preparing',
        'warehouse_requested' => 'preparing',
        'warehouses_assigned' => 'preparing',
        'courier_assigned' => 'preparing',
    ];

    /** Müştəriyə göstərilən status (daxili mərhələlər "Hazırlanır"-a çevrilir) */
    public function forCustomer(): self
    {
        $code = self::CUSTOMER_MAP[$this->code] ?? null;
        if (!$code) {
            return $this;
        }
        static $cache = [];

        return $cache[$code] ??= (static::where('code', $code)->first() ?? $this);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        $column = in_array($locale, ['az', 'en', 'ru'], true)
            ? 'name_' . $locale
            : 'name_az';

        return $this->{$column} ?: $this->name_az ?: '';
    }
}
