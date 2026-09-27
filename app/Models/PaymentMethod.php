<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = in_array(app()->getLocale(), ['az', 'en', 'ru'], true)
            ? app()->getLocale()
            : 'az';

        return $this->getAttribute('name_'.$locale)
            ?: $this->getAttribute('name_az')
            ?: $this->getAttribute('name')
            ?: $this->getAttribute('code');
    }
}
