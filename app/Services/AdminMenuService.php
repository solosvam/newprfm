<?php

namespace App\Services;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class AdminMenuService
{
    public function items(): array
    {
        return $this->visible(config('admin_menu', []));
    }

    private function visible(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!empty($item['permission']) && Gate::forUser(auth('admin')->user())->denies($item['permission'])) {
                continue;
            }

            if (isset($item['children'])) {
                $item['children'] = $this->visible($item['children']);
                if (!$item['children']) {
                    continue;
                }
            } elseif (empty($item['route']) || !Route::has($item['route'])) {
                continue;
            }

            $result[] = $item;
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
