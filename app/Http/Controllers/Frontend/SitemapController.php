<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Product;
use App\Support\LocaleUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml — aktiv kateqoriyalar, brendlər, məhsullar və məlumat səhifələri;
 * hər ünvan üçün dil versiyaları (xhtml:link hreflang). public/robots.txt — `php artisan seo:robots` yaradır.
 */
class SitemapController extends Controller
{
    private const TTL = 3600;

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', self::TTL, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $entries = [[route('home'), null, '1.0']];
        foreach (Category::where('active', 1)->orderBy('id')->get(['slug']) as $category) {
            $entries[] = [route('category', ['slug' => $category->slug]), null, '0.8'];
        }
        $entries[] = [route('brands'), null, '0.6'];
        foreach (Brand::where('active', 1)->orderBy('name')->get(['slug']) as $brand) {
            $entries[] = [route('brand.products', ['slug' => $brand->slug]), null, '0.6'];
        }
        // products cədvəlində updated_at yoxdur — lastmod göstərilmir
        foreach (Product::where('active', 1)->orderBy('id')->pluck('slug') as $slug) {
            $entries[] = [route('product', ['slug' => $slug]), null, '0.7'];
        }
        foreach (Page::all(['key', 'updated_at']) as $page) {
            if (isset(Page::PAGES[$page->key])) {
                $entries[] = [$page->url(), $page->updated_at, '0.3'];
            }
        }
        foreach (['front.faq', 'front.bonus', 'front.installment', 'front.referral'] as $name) {
            $entries[] = [route($name), null, '0.3'];
        }

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($entries as [$url, $lastmod, $priority]) {
            foreach (LocaleUrl::LOCALES as $locale) {
                $out .= "  <url>\n    <loc>".e(LocaleUrl::to($url, $locale))."</loc>\n";
                foreach (LocaleUrl::LOCALES as $alt) {
                    $out .= '    <xhtml:link rel="alternate" hreflang="'.$alt.'" href="'.e(LocaleUrl::to($url, $alt)).'"/>'."\n";
                }
                $out .= '    <xhtml:link rel="alternate" hreflang="x-default" href="'.e($url).'"/>'."\n";
                if ($lastmod) {
                    $out .= '    <lastmod>'.$lastmod->toAtomString()."</lastmod>\n";
                }
                $out .= "    <priority>{$priority}</priority>\n  </url>\n";
            }
        }

        return $out.'</urlset>'."\n";
    }
}
