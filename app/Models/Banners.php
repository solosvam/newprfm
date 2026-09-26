<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banners extends Model
{
    use HasFactory;
    protected $table = 'banners';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'url',
        'url_az',
        'url_en',
        'url_ru',
        'location',
        'device',
        'active',
    ];
    public function imageForLocale(?string $locale = null): ?string
    {
        $locale = in_array($locale, ['az', 'en', 'ru'], true)
            ? $locale
            : app()->getLocale();

        return $this->{'url_' . $locale}
            ?: $this->url_az
            ?: $this->url;
    }
}
