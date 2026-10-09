<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use App\Services\ProductDeleter;
use Illuminate\Console\Command;

class DeleteProductsByType extends Command
{
    protected $signature = 'parfumshop:delete-products-by-type
        {from : Başlanğıc type_id}
        {to : Son type_id (daxil)}
        {--apply : Həqiqətən sil (olmasa yalnız göstərir)}';

    protected $description = 'type_id aralığındakı məhsulları bağlı məlumatlar və şəkil faylları ilə birlikdə silir';

    public function handle(ProductDeleter $deleter): int
    {
        $from = (int) $this->argument('from');
        $to = (int) $this->argument('to');

        if ($from < 1 || $to < $from) {
            $this->error('Aralıq yanlışdır.');
            return self::FAILURE;
        }

        $products = Product::with('type')->withCount('images')
            ->whereBetween('type_id', [$from, $to])
            ->orderBy('id')
            ->get();

        $this->line("type_id {$from}–{$to}: {$products->count()} məhsul, {$products->sum('images_count')} şəkil");

        foreach ($products->groupBy(fn (Product $p) => $p->type_id.' '.($p->type?->name_az ?? '?')) as $type => $items) {
            $this->line("  #{$type}: {$items->count()}");
        }

        $blocked = $products->mapWithKeys(fn (Product $p) => [$p->id => $deleter->blockedReason($p)])->filter();

        foreach ($blocked as $id => $reason) {
            $product = $products->firstWhere('id', $id);
            $this->warn("  Silinməyəcək: #{$id} {$product->name} (old_id {$product->old_id}) — {$reason}");
        }

        $deletable = $products->reject(fn (Product $p) => $blocked->has($p->id));
        $this->line("Silinəcək: {$deletable->count()}, silinməyəcək: {$blocked->count()}");

        if (!$this->option('apply')) {
            $this->warn('Yoxlama rejimi — heç nə silinmədi. Silmək üçün --apply əlavə edin.');
            return self::SUCCESS;
        }

        $files = 0;
        $bar = $this->output->createProgressBar($deletable->count());

        foreach ($deletable as $product) {
            $files += $deleter->delete($product);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Silindi: {$deletable->count()} məhsul, {$files} şəkil faylı.");

        return self::SUCCESS;
    }
}
