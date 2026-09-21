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
use Throwable;

class ImportOldParfumshopCategory extends Command
{
    protected $signature = 'parfumshop:import-category {path : Köhnə saytdakı category path} {--category-id= : Yeni saytdakı category ID} {--dry-run : DB və fayllara yazma} {--limit= : Import ediləcək maksimum məhsul sayı}';
    protected $description = 'Köhnə ParfumShop kateqoriyasındakı məhsulları yeni sistemə import edir';

    private string $base = 'https://www.parfumshop.az';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $urls = $this->productUrls($path);

        if ($this->option('limit') !== null) {
            $limit = max(1, (int) $this->option('limit'));
            $urls = array_slice($urls, 0, $limit);
        }

        if (!$urls) {
            $this->error('Kateqoriyada məhsul tapılmadı.');
            return self::FAILURE;
        }

        $this->info(count($urls).' məhsul tapıldı.');

        foreach ($urls as $i => $url) {
            try {
                $data = $this->parseProduct($url);
                $this->line('['.($i + 1).'/'.count($urls).'] '.$data['brand'].' — '.$data['name'].' (old_id: '.$data['old_id'].')');

                if ($this->option('dry-run')) {
                    $this->line('  '.$data['type'].' | '.$data['gender'].' | '.implode(', ', array_map(fn ($v) => $v['size_az'].' = '.$v['price'].' AZN', $data['variants'])).' | '.count($data['images']).' şəkil');
                    continue;
                }

                $this->storeProduct($data);
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

        // Kateqoriya məhsullarının detail düyməsi köhnə temada "ƏTRAFLI" mətnidir.
        // Şəkil/ad linkləri səhifədə əlavə bloklarda da təkrarlana bildiyi üçün yalnız
        // bu düymələri götürürük.
        foreach ($xpath->query('//a[@href]') as $a) {
            $label = mb_strtoupper(trim(preg_replace('/\\s+/u', ' ', $a->textContent)), 'UTF-8');
            if ($label !== 'ƏTRAFLI') {
                continue;
            }

            $href = html_entity_decode($a->getAttribute('href'));
            if (!preg_match('/[?&]product_id=(\\d+)/', $href)) {
                continue;
            }

            $urls[] = $this->absoluteUrl($href);
        }

        return array_values(array_unique($urls));
    }

    private function parseProduct(string $url): array
    {
        preg_match('/product_id=(\\d+)/', html_entity_decode($url), $id);
        if (empty($id[1])) {
            throw new \RuntimeException('product_id tapılmadı: '.$url);
        }

        $response = Http::acceptJson()
            ->timeout(30)
            ->retry(3, 500)
            ->get($this->base.'/migration-product.php', [
                'product_id' => (int) $id[1],
            ])
            ->throw()
            ->json();

        if (!($response['success'] ?? false) || empty($response['product'])) {
            throw new \RuntimeException('Migration API məhsulu qaytarmadı: '.$id[1]);
        }

        $p = $response['product'];

        return [
            'old_id' => (int) $p['old_id'],
            'brand' => trim((string) ($p['brand']['name_az'] ?? $p['brand']['name'] ?? '')),
            'name' => trim((string) ($p['model'] ?? $p['name'] ?? '')),
            'type' => trim((string) ($p['type'] ?? '')),
            'gender' => trim(str_replace(' üçün', '', (string) ($p['gender'] ?? ''))),
            'description_az' => $this->decodeHtml((string) ($p['description_az'] ?? $p['description'] ?? '')),
            'description_ru' => $this->decodeHtml((string) ($p['description_ru'] ?? '')),
            'variants' => array_map(fn ($v) => [
                'size_az' => trim((string) ($v['size_az'] ?? $v['size'] ?? 'Standart')) ?: 'Standart',
                'size_ru' => trim((string) ($v['size_ru'] ?? $v['size_az'] ?? $v['size'] ?? 'Стандарт')) ?: 'Стандарт',
                'price' => (float) ($v['price'] ?? 0),
            ], $p['variants'] ?? []),
            'categories' => $p['categories'] ?? [],
            'images' => array_values(array_filter(array_map(
                fn ($image) => $image['url'] ?? null,
                $p['images'] ?? []
            ))),
            'url' => $url,
        ];
    }

    private function storeProduct(array $data): void
    {
        DB::transaction(function () use ($data) {
            $brand = Brand::firstOrCreate(['name'=>$data['brand']], ['active'=>1]);
            $type = Type::firstOrCreate(['name_az'=>$data['type'] ?: 'Digər'], ['name_en'=>$data['type'] ?: 'Other','name_ru'=>$data['type'] ?: 'Другое']);
            $gender = $data['gender'] ? Gender::firstOrCreate(['name_az'=>$data['gender']], ['name_en'=>$data['gender'],'name_ru'=>$data['gender']]) : null;

            $product = Product::updateOrCreate(['old_id'=>$data['old_id']], [
                'brand_id'=>$brand->id, 'type_id'=>$type->id, 'name'=>$data['name'],
                'content_az'=>$data['description_az'], 'content_ru'=>$data['description_ru'], 'content_en'=>'', 'active'=>1,
            ]);

            $categoryMap = [35=>1, 36=>2, 37=>3, 39=>4, 55=>5, 41=>6, 44=>7];
            $categoryIds = collect($data['categories'] ?? [])->pluck('old_id')->map(fn ($oldId) => $categoryMap[(int) $oldId] ?? null)->filter()->unique()->values()->all();
            if ($categoryIds) $product->categories()->syncWithoutDetaching($categoryIds);
            if ($gender) $product->genders()->syncWithoutDetaching([$gender->id]);

            foreach ($data['variants'] as $variant) {
                $size = Size::firstOrCreate(['name_az'=>$variant['size_az']], ['name_en'=>$variant['size_az'],'name_ru'=>$variant['size_ru']]);
                ProductVariant::updateOrCreate(
                    ['product_id'=>$product->id,'size_id'=>$size->id],
                    ['price'=>$variant['price'],'active'=>1]
                );
            }

            if (!$product->images()->exists()) $this->downloadImages($product, $data['images']);
        });
    }

    private function decodeHtml(string $html): string
    {
        // Köhnə sistemdə bir neçə dəfə encode olunmuş entity-ləri aç.
        for ($i = 0; $i < 5; $i++) {
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $html) {
                break;
            }

            $html = $decoded;
        }

        // Non-breaking space-i adi boşluğa çevir.
        $html = str_replace("\xC2\xA0", ' ', $html);

        // Məna daşıyan HTML elementlərini sətir sonuna çevir.
        $html = preg_replace('~<br\\s*/?>~i', "\n", $html);
        $html = preg_replace('~</p\\s*>~i', "\n", $html);
        $html = preg_replace('~<li[^>]*>~i', '• ', $html);
        $html = preg_replace('~</li\\s*>~i', "\n", $html);

        // Qalan bütün HTML tag-larını sil.
        $text = strip_tags($html);

        // Artıq boşluqları və boş sətirləri təmizlə.
        $text = preg_replace('/[ \\t]+/u', ' ', $text);
        $text = preg_replace('/ *\\n */u', "\n", $text);
        $text = preg_replace('/\\n+/u', "\n", $text);

        return trim($text);
    }

    private function downloadImages(Product $product, array $urls): void
    {
        if (!$urls) return;
        $dir = public_path('frontend/uploads/products');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $base = SeoUrl::generateImageName(['title'=>$product->brand->name.'-'.$product->name]);

        foreach ($urls as $i => $url) {
            try {
                $bytes = Http::timeout(30)->retry(2, 500)->get($url)->throw()->body();
                $name = $base.'-'.($i + 1).'.webp';
                file_put_contents($dir.'/'.$name, $bytes);
                ProductImage::create(['product_id'=>$product->id,'image'=>$name]);
            } catch (Throwable $e) {
                $this->warn('  Şəkil yüklənmədi: '.$url);
                $this->warn('  Səbəb: '.$e->getMessage());
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
