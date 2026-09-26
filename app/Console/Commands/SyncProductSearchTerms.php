<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use App\Services\Search\ProductSearchService;
use App\Services\Search\ProductSearchTermSynchronizer;
use Illuminate\Console\Command;

class SyncProductSearchTerms extends Command
{
    protected $signature = 'products:sync-search-terms {product? : Yalnız bir məhsulun ID-si}';

    protected $description = 'Məhsul və brend adlarından axtarış lüğətini yeniləyir';

    public function handle(ProductSearchTermSynchronizer $synchronizer, ProductSearchService $search): int
    {
        $productId = $this->argument('product');
        $query = Product::query()->with('brand')->orderBy('id');

        if ($productId !== null) {
            $query->whereKey((int) $productId);
        }

        $productCount = $query->count();

        if ($productCount === 0) {
            $this->warn('Məhsul tapılmadı.');
            return self::FAILURE;
        }

        $termCount = 0;
        $bar = $this->output->createProgressBar($productCount);
        $bar->start();

        $query->chunkById(100, function ($products) use ($synchronizer, &$termCount, $bar) {
            foreach ($products as $product) {
                $termCount += $synchronizer->sync($product);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $search->forgetCachedTerms();
        $this->info("{$productCount} məhsul üçün {$termCount} axtarış ifadəsi yaradıldı.");

        return self::SUCCESS;
    }
}
