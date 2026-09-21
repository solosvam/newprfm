<?php

namespace App\Console\Commands;

use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Gender;
use App\Models\Product\Product;
use App\Models\Product\ProductImage;
use App\Models\Product\ProductVariant;
use App\Models\Product\Size;
use App\Models\Product\Type;
use App\Services\SeoUrl;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class ImportOldParfumshopCategory extends Command
{
    protected $signature = 'parfumshop:import-category {path : Köhnə saytdakı category path} {--category-id= : Yeni saytdakı category ID} {--dry-run : DB və fayllara yazma}';
    protected $description = 'Köhnə ParfumShop kateqoriyasındakı məhsulları yeni sistemə import edir';

    private string $base = 'https://www.parfumshop.az';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $urls = $this->productUrls($path);

        if (!$urls) {
            $this->error('Kateqoriyada məhsul tapılmadı.');
            return self::FAILURE;
        }

        $category = $this->resolveCategory($path);
        if (!$category && !$this->option('dry-run')) {
            $this->error('Yeni saytda uyğun kateqoriya tapılmadı. --category-id=ID ver.');
            return self::FAILURE;
        }

        $this->info(count($urls).' məhsul tapıldı'.($category ? ' → '.$category->name_az : '').'.');

        foreach ($urls as $i => $url) {
            try {
                $data = $this->parseProduct($url);
                $this->line('['.($i + 1).'/'.count($urls).'] '.$data['brand'].' — '.$data['name'].' (old_id: '.$data['old_id'].')');

                if ($this->option('dry-run')) {
                    $this->line('  '.$data['type'].' | '.$data['gender'].' | '.implode(', ', array_map(fn ($v) => $v['size'].' = '.$v['price'].' AZN', $data['variants'])).' | '.count($data['images']).' şəkil');
                    continue;
                }

                $this->storeProduct($data, $category);
            } catch (Throwable $e) {
                $this->error('  Xəta: '.$e->getMessage());
            }
        }

        $this->info($this->option('dry-run') ? 'Dry-run tamamlandı.' : 'Import tamamlandı.');
        return self::SUCCESS;
    }

    private function productUrls(string $path): array
    {
        $url = $this->base.'/index.php?route=product/category&path='.urlencode($path);
        $xpath = $this->xpath($this->get($url));
        $urls = $this->productLinksFromGrid($xpath);

        // Yalnız səhifədə real pagination linkləri varsa onları gəz.
        $pages = [1];
        foreach ($xpath->query('//ul[contains(@class,"pagination")]//a[@href]') as $a) {
            $href = html_entity_decode($a->getAttribute('href'));
            if (preg_match('/[?&]page=(\\d+)/', $href, $m)) {
                $pages[] = (int) $m[1];
            }
        }

        $maxPage = max($pages);
        for ($page = 2; $page <= $maxPage; $page++) {
            $pageXpath = $this->xpath($this->get($url.'&page='.$page));
            $urls = array_merge($urls, $this->productLinksFromGrid($pageXpath));
        }

        return array_values(array_unique($urls));
    }

    private function productLinksFromGrid(DOMXPath $xpath): array
    {
        $urls = [];

        // Köhnə tema standart OpenCart product-layout class istifadə etmir.
        // Məhsul detail linklərini götürürük, amma yalnız əsas content hissəsindən;
        // footer/menu linklərində product_id olmadığı üçün onlar avtomatik kənarda qalır.
        foreach ($xpath->query('//a[@href]') as $a) {
            $href = html_entity_decode($a->getAttribute('href'));

            if (!preg_match('/[?&]product_id=(\\d+)/', $href)) {
                continue;
            }

            if (!preg_match('/[?&]route=(?:product\\/product|product%2Fproduct)/i', $href)) {
                continue;
            }

            $urls[] = $this->absoluteUrl($href);
        }

        return array_values(array_unique($urls));
    }

    private function parseProduct(string $url): array
    {
        $html = $this->get($url);
        $xpath = $this->xpath($html);
        preg_match('/product_id=(\d+)/', html_entity_decode($url), $id);

        $text = trim(preg_replace('/\s+/u', ' ', $xpath->document->textContent));
        $brand = $this->labelValue($text, 'Brend:', ['Model:']);
        $name = $this->labelValue($text, 'Model:', ['Say', 'Həcmi']);
        $type = $this->firstMatchingText($xpath, ['Eau De Parfum','Eau De Toilette','Extrait De Parfum','Parfum','Cologne']);
        $gender = str_contains($text, 'Qadın üçün') ? 'Qadın' : (str_contains($text, 'Kişi üçün') ? 'Kişi' : (str_contains($text, 'Unisex') ? 'Unisex' : ''));

        $description = '';
        foreach ($xpath->query('//*[contains(@id,"tab-description") or contains(@class,"tab-description")]') as $node) {
            $description = trim($node->textContent);
            if ($description) break;
        }
        if (!$description && preg_match('/Açıqlama\s+(.*?)\s+Şərh yaz/su', $text, $m)) $description = trim($m[1]);

        $variants = $this->variants($xpath, $text);
        $images = $this->images($xpath);

        if (!$id || !$brand || !$name) throw new \RuntimeException('Məhsul məlumatı tam oxunmadı: '.$url);

        return compact('brand','name','type','gender','description','variants','images') + ['old_id'=>(int)$id[1], 'url'=>$url];
    }

    private function variants(DOMXPath $xpath, string $text): array
    {
        $sizes = [];
        foreach ($xpath->query('//select[contains(@name,"option") or contains(@id,"input-option")]//option[@value]') as $option) {
            $label = trim(preg_replace('/\s+/u', ' ', $option->textContent));
            if (!$option->getAttribute('value') || preg_match('/seç/i', $label)) continue;
            $label = preg_replace('/\s*\([+-]?\s*[\d.,]+\s*AZN\)\s*/iu', '', $label);
            if ($label) $sizes[] = $label;
        }
        $sizes = array_values(array_unique($sizes));

        preg_match_all('/([\d]+(?:[.,]\d{1,2})?)\s*AZN/u', $text, $prices);
        $price = isset($prices[1][0]) ? (float)str_replace(',', '.', $prices[1][0]) : 0;

        if (!$sizes) $sizes = ['Standart'];
        return array_map(fn ($size) => ['size'=>$size, 'price'=>$price], $sizes);
    }

    private function images(DOMXPath $xpath): array
    {
        $images = [];
        foreach ($xpath->query('//a[@href] | //img[@src]') as $node) {
            $src = $node->hasAttribute('href') ? $node->getAttribute('href') : $node->getAttribute('src');
            $src = html_entity_decode($src);
            if (!preg_match('/\.(jpe?g|png|webp)(\?.*)?$/i', $src)) continue;
            if (!preg_match('~(?:catalog|cache).*(?:product|perfume|parfum|image)~i', $src)) continue;
            $images[] = $this->absoluteUrl($src);
        }
        return array_values(array_unique($images));
    }

    private function storeProduct(array $data, Category $category): void
    {
        DB::transaction(function () use ($data, $category) {
            $brand = Brand::firstOrCreate(['name'=>$data['brand']], ['active'=>1]);
            $type = Type::firstOrCreate(['name_az'=>$data['type'] ?: 'Digər'], ['name_en'=>$data['type'] ?: 'Other','name_ru'=>$data['type'] ?: 'Другое']);
            $gender = $data['gender'] ? Gender::firstOrCreate(['name_az'=>$data['gender']], ['name_en'=>$data['gender'],'name_ru'=>$data['gender']]) : null;

            $product = Product::updateOrCreate(['old_id'=>$data['old_id']], [
                'brand_id'=>$brand->id, 'type_id'=>$type->id, 'name'=>$data['name'],
                'content_az'=>$data['description'], 'active'=>1,
            ]);

            $product->categories()->syncWithoutDetaching([$category->id]);
            if ($gender) $product->genders()->syncWithoutDetaching([$gender->id]);

            foreach ($data['variants'] as $variant) {
                $size = Size::firstOrCreate(['name_az'=>$variant['size']], ['name_en'=>$variant['size'],'name_ru'=>$variant['size']]);
                ProductVariant::updateOrCreate(
                    ['product_id'=>$product->id,'size_id'=>$size->id],
                    ['price'=>$variant['price'],'active'=>1]
                );
            }

            if (!$product->images()->exists()) $this->downloadImages($product, $data['images']);
        });
    }

    private function downloadImages(Product $product, array $urls): void
    {
        if (!$urls) return;
        $dir = public_path('frontend/uploads/products');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $manager = ImageManager::usingDriver(Driver::class);
        $base = SeoUrl::generateImageName(['title'=>$product->brand->name.'-'.$product->name]);

        foreach ($urls as $i => $url) {
            try {
                $bytes = Http::timeout(30)->retry(2, 500)->get($url)->throw()->body();
                $name = $base.'-'.($i + 1).'.webp';
                $manager->read($bytes)->cover(600, 600)->toWebp(82)->save($dir.'/'.$name);
                ProductImage::create(['product_id'=>$product->id,'image'=>$name]);
            } catch (Throwable $e) {
                $this->warn('  Şəkil yüklənmədi: '.$url);
            }
        }
    }

    private function resolveCategory(string $path): ?Category
    {
        if ($id = $this->option('category-id')) return Category::find((int)$id);

        $xpath = $this->xpath($this->get($this->base.'/index.php?route=product/category&path='.urlencode($path)));
        $title = trim($xpath->evaluate('string(//h1[1])'));
        if (!$title) $title = trim($xpath->evaluate('string(//title)'));
        $title = str_replace('_', ' ', preg_replace('/\s*\|.*$/u', '', $title));

        return Category::whereRaw('LOWER(name_az) = ?', [mb_strtolower($title)])->first()
            ?? Category::where('name_az', 'like', '%'.$title.'%')->first();
    }

    private function get(string $url): string
    {
        return Http::withHeaders(['User-Agent'=>'Mozilla/5.0 ParfumShop Migration'])->timeout(30)->retry(3, 500)->get($url)->throw()->body();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new DOMXPath($dom);
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, '//')) return 'https:'.$url;
        if (preg_match('~^https?://~i', $url)) return $url;
        return $this->base.'/'.ltrim($url, '/');
    }

    private function labelValue(string $text, string $label, array $until): string
    {
        $start = mb_strpos($text, $label);
        if ($start === false) return '';
        $value = mb_substr($text, $start + mb_strlen($label));
        $end = mb_strlen($value);
        foreach ($until as $marker) {
            $p = mb_strpos($value, $marker);
            if ($p !== false) $end = min($end, $p);
        }
        return trim(mb_substr($value, 0, $end));
    }

    private function firstMatchingText(DOMXPath $xpath, array $values): string
    {
        $text = $xpath->document->textContent;
        foreach ($values as $value) if (stripos($text, $value) !== false) return $value;
        return '';
    }
}
