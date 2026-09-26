<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const BANNER_DIMENSIONS = [
        'banner_web_top' => [1920, 370],
        'banner_web_bottom' => [1920, 300],
        'banner_mobile_top' => [790, 300],
        'banner_mobile_bottom' => [790, 220],
    ];

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
