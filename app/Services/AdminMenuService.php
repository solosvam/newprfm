<?php

namespace App\Services;

use App\Models\Product\ProductReview;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class AdminMenuService
{
    /** Menyu bəndlərinin yanındakı saylar (badge açarı => say) */
    private function badgeCount(string $key): int
    {
        return match ($key) {
            'reviews.pending' => ProductReview::where('active', false)->count(),
            default => 0,
        };
    }

    public function items(): array
    {
        return $this->visible(config('admin_menu', []));
    }

    private function visible(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!empty($item['separator'])) {
                // ayırıcı: əvvəlində və ardıcıl gələndə lazım deyil
                if ($result && empty(end($result)['separator'])) {
                    $result[] = $item;
                }
                continue;
            }
            if (!empty($item['permission']) && Gate::forUser(auth('admin')->user())->denies($item['permission'])) {
                continue;
            }

            if (isset($item['children'])) {
                $item['children'] = $this->visible($item['children']);
                if (!$item['children']) {
                    continue;
                }
                // bölmənin sayı — içindəki bəndlərin cəmi
                $item['badge_count'] = array_sum(array_map(fn ($child) => $child['badge_count'] ?? 0, $item['children']));
            } elseif (empty($item['route']) || !Route::has($item['route'])) {
                continue;
            } else {
                $item['badge_count'] = !empty($item['badge']) ? $this->badgeCount($item['badge']) : 0;
            }

            $result[] = $item;
        }
        // sonda qalan ayırıcı (məs. Statistika icazəsi yoxdursa)
        if ($result && !empty(end($result)['separator'])) {
            array_pop($result);
        }

        return $result;
    }

    public function searchPages(): array
    {
        return $this->flatten($this->items());
    }

    private function flatten(array $items, string $parent = ''): array
    {
        $pages = [];
        foreach ($items as $item) {
            if (!empty($item['separator'])) {
                continue;
            }
            $label = $parent === '' ? $item['title'] : $parent.' > '.$item['title'];
            if (isset($item['children'])) {
                array_push($pages, ...$this->flatten($item['children'], $label));
            } else {
                $pages[] = ['label' => $label, 'url' => route($item['route'])];
            }
        }
        return $pages;
    }
}
