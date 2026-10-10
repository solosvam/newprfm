<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Bannerlərin standart ölçüsü (ayar saxlanmayıbsa). Saytda göstərilən ölçünün iki mislidir — yüksək sıxlıqlı
     * ekranlar üçün: veb 1060×320 / 1060×220, mobil 640×280 / 640×220.
     */
    public const BANNER_DIMENSIONS = [
        'banner_web_top' => [2120, 640],
        'banner_web_bottom' => [2120, 440],
        'banner_mobile_top' => [1280, 560],
        'banner_mobile_bottom' => [1280, 440],
    ];

    /**
     * Banner ölçüsünün hədləri. En standartdan (saytda göstərilən enin iki misli) böyük ola bilməz — daha böyük şəkil
     * keyfiyyəti artırmır, yalnız səhifəni ağırlaşdırır. Nisbət (en ÷ hündürlük) saytda banner blokunun formasını
     * müəyyən edir: çox hündür banner ilk ekranı tutur, çox nazik banner oxunmur.
     *
     * @return array{display_width: int, max_width: int, min_width: int, min_ratio: float, max_ratio: float}
     */
    public static function bannerLimits(string $key): array
    {
        $max = self::BANNER_DIMENSIONS[$key][0];
        $mobile = str_contains($key, 'mobile');

        return [
            'display_width' => intdiv($max, 2),
            'max_width' => $max,
            'min_width' => intdiv($max, 2),
            'min_ratio' => $mobile ? 1.8 : 2.5,
            'max_ratio' => $mobile ? 4.0 : 6.0,
        ];
    }

    public static function valueOf(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function bannerDimensions(string $device, string $location): array
    {
        $key = "banner_{$device}_{$location}";

        if (!array_key_exists($key, self::BANNER_DIMENSIONS)) {
            throw new \InvalidArgumentException('Naməlum banner ölçüsü.');
        }

        [$defaultWidth, $defaultHeight] = self::BANNER_DIMENSIONS[$key];

        return [
            (int) static::valueOf("{$key}_width", $defaultWidth),
            (int) static::valueOf("{$key}_height", $defaultHeight),
        ];
    }
}
