<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use App\Models\Product\ProductSearchTerm;
use App\Services\OpenAiPerfumeService;
use App\Services\Search\ProductSearchNormalizer;
use App\Services\Search\ProductSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateAiProductSearchTerms extends Command
{
    protected $signature = 'products:generate-ai-search-terms
                            {product? : Yalnız bir məhsulun ID-si}
                            {--fresh : Mövcud product_search_terms qeydlərini silib yenidən yaradır}
                            {--limit= : Emal ediləcək maksimum məhsul sayı}';

    protected $description = 'Məhsullar üçün AI ilə 5-15 axtarış yazılışı yaradır';

    public function handle(OpenAiPerfumeService $openAi, ProductSearchService $search): int
    {
        $productId = $this->argument('product');

        if ($productId !== null && $this->option('fresh')) {
            $this->error('--fresh yalnız bütün məhsulları yenidən yaratmaq üçündür.');
            return self::FAILURE;
        }

        $query = Product::query()->with('brand')->orderBy('id');

        if ($productId !== null) {
            $query->whereKey((int) $productId);
        } elseif (!$this->option('fresh')) {
            $query->whereDoesntHave('searchTerms', fn ($termQuery) => $termQuery->where('source', 'ai'));
        }

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('Emal ediləcək məhsul tapılmadı.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            ProductSearchTerm::query()->delete();
            $this->warn('Köhnə axtarış terminləri silindi.');
        }

        $termCount = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        foreach ($products as $product) {
            try {
                $termCount += $this->generateForProduct($product, $openAi);
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->newLine();
                $this->error("#{$product->id} {$product->name}: {$exception->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $search->forgetCachedTerms();
        $this->info("{$products->count()} məhsul üçün {$termCount} AI axtarış ifadəsi yaradıldı.");

        if ($failed > 0) {
            $this->warn("{$failed} məhsul alınmadı. Eyni command-i --fresh olmadan yenidən işə sal.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function generateForProduct(Product $product, OpenAiPerfumeService $openAi): int
    {
        $brand = trim((string) $product->brand?->name);
        $name = trim((string) $product->name);

        if ($brand === '' || $name === '') {
            throw new \RuntimeException('Brend və ya məhsul adı boşdur.');
        }

        $values = array_merge(["{$brand} {$name}"], $openAi->generateSearchTerms($brand, $name));
        $terms = [];

        foreach ($values as $index => $value) {
            $value = trim((string) $value);
            $normalized = ProductSearchNormalizer::normalize($value);

            if ($normalized === '' || isset($terms[$normalized])) {
                continue;
            }

            $terms[$normalized] = [
                'term' => mb_substr($value, 0, 255),
                'normalized_term' => $normalized,
                'phonetic_term' => ProductSearchNormalizer::phonetic($value),
                'token_signature' => ProductSearchNormalizer::tokenSignature($value),
                'source' => 'ai',
                'priority' => $index === 0 ? 1000 : max(600, 900 - $index * 20),
                'active' => (bool) $product->active,
            ];
        }

        DB::transaction(function () use ($product, $terms) {
            $product->searchTerms()->delete();
            $product->searchTerms()->createMany(array_values($terms));
        });

        return count($terms);
    }
}
