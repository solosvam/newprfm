<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * İcazələri admin səhifələrində bölmələrə ayırır: adın nöqtəyə qədər hissəsi (site.banners → site).
 * Siyahıda olmayan prefiks "Digər" bölməsinə düşür.
 */
class PermissionGroups
{
    public const GROUPS = [
        'admin' => ['İdarəetmə', 'gear'],
        'sales' => ['Satış və müştərilər', 'cart'],
        'finance' => ['Maliyyə və tərəfdaşlar', 'wallet'],
        'catalog' => ['Məhsullar', 'gift'],
        'site' => ['Sayt', 'screen'],
        'other' => ['Digər', 'more-horizontal'],
    ];

    public const PREFIXES = [
        'admin' => 'admin', 'user' => 'admin', 'role' => 'admin', 'permission' => 'admin', 'system' => 'admin',
        'crm' => 'sales', 'credit' => 'sales', 'promo' => 'sales', 'refund' => 'sales',
        'finance' => 'finance', 'ferrum' => 'finance',
        'products' => 'catalog', 'product' => 'catalog', 'size' => 'catalog', 'category' => 'catalog',
        'brands' => 'catalog', 'ingredient' => 'catalog', 'type' => 'catalog', 'gender' => 'catalog',
        'site' => 'site',
    ];

    public static function keyFor(string $permission): string
    {
        return self::PREFIXES[strtok($permission, '.')] ?? 'other';
    }

    public static function label(string $key): string
    {
        return self::GROUPS[$key][0] ?? self::GROUPS['other'][0];
    }

    /** @return Collection<string, array{key: string, label: string, icon: string, items: Collection}> sabit sıra ilə */
    public static function group(Collection $permissions): Collection
    {
        $byKey = $permissions->sortBy('name')->groupBy(fn ($p) => self::keyFor($p->name));

        return collect(self::GROUPS)->keys()->filter(fn ($key) => $byKey->has($key))
            ->mapWithKeys(fn ($key) => [$key => [
                'key' => $key, 'label' => self::GROUPS[$key][0], 'icon' => self::GROUPS[$key][1], 'items' => $byKey[$key]->values(),
            ]]);
    }
}
