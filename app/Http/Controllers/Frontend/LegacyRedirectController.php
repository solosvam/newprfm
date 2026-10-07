<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Köhnə OpenCart saytının ünvanları (/index.php?route=...) → yeni səhifələr, 301.
 * Xəritə: database/data/legacy_urls.php. Köhnə rus versiyası (&language=ru) → ?lang=ru.
 * Tanınmayan route — 404 (hamısını ana səhifəyə atmaq Google-da "soft 404" sayılır).
 */
class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $map = require database_path('data/legacy_urls.php');
        $route = (string) $request->query('route', 'common/home');
        $id = fn (string $key) => ctype_digit((string) $request->query($key)) ? (int) $request->query($key) : null;

        $url = match (true) {
            $route === 'common/home' => route('home'),

            $route === 'product/product' => ($product = Product::where('old_id', $id('product_id') ?? 0)->first())
                ? route('product', ['slug' => $product->slug]) : null,

            $route === 'information/information' => isset($map['information'][$id('information_id')])
                ? route($map['information'][$id('information_id')]) : route('home'),
            $route === 'information/contact' => route('front.page.contact'),

            $route === 'product/category' => $this->category($map, (string) $request->query('path')),

            $route === 'product/manufacturer' => ($slug = $map['manufacturer'][$id('manufacturer_id')] ?? null)
                && Brand::where('slug', $slug)->exists()
                ? route('brand.products', ['slug' => $slug]) : route('brands'),

            $route === 'product/search' => route('home', array_filter(['q' => trim((string) ($request->query('keyword') ?? $request->query('search')))])),

            str_starts_with($route, 'checkout/') => route('cart'),
            str_starts_with($route, 'account/') => auth()->check() ? route('profile') : route('front.login'),

            default => null,
        };

        abort_unless($url, 404);

        // köhnə saytın rus versiyası
        if ($request->query('language') === 'ru') {
            $url .= (str_contains($url, '?') ? '&' : '?').'lang=ru';
        }

        return redirect()->to($url, 301);
    }

    private function category(array $map, string $path): string
    {
        $first = (int) explode('_', $path)[0];
        $slug = $map['category'][$first] ?? null;

        return $slug && Category::where('slug', $slug)->where('active', 1)->exists()
            ? route('category', ['slug' => $slug])
            : route('home');
    }
}
