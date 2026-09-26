<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    protected $guarded = [];

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
