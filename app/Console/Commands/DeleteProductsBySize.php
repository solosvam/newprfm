<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use App\Models\Product\Size;
use App\Services\ProductDeleter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adı verilən ölçüyə (standart: "delete") bağlı variantı olan məhsulları bağlı məlumatlar və şəkil faylları
 * ilə birlikdə silir; sonra boşalan həmin ölçüləri də silir.
 */
class DeleteProductsBySize extends Command
{
    protected $signature = 'parfumshop:delete-products-by-size
        {name=delete : sizes.name_az dəyəri}
        {--apply : Həqiqətən sil (olmasa yalnız göstərir)}';

    protected $description = 'Ölçü adına görə (sizes.name_az) məhsulları bağlı məlumatlar və şəkil faylları ilə birlikdə silir';

    public function handle(ProductDeleter $deleter): int
    {
        $name = (string) $this->argument('name');
        $sizeIds = Size::where('name_az', $name)->pluck('id');

        if ($sizeIds->isEmpty()) {
            $this->warn("name_az = '{$name}' olan ölçü yoxdur.");
            return self::SUCCESS;
        }

        $products = Product::withCount('images')
            ->whereHas('variants', fn ($query) => $query->whereIn('size_id', $sizeIds))
            ->withCount(['variants as other_variants_count' => fn ($query) => $query->whereNotIn('size_id', $sizeIds)])
            ->orderBy('id')
            ->get();

        $this->line("name_az = '{$name}': {$sizeIds->count()} ölçü, {$products->count()} məhsul, {$products->sum('images_count')} şəkil");

        foreach ($products->where('other_variants_count', '>', 0) as $product) {
            $this->warn("  Diqqət: #{$product->id} {$product->name} — başqa ölçüləri də var ({$product->other_variants_count} variant), bütün məhsul silinəcək");
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

        $emptySizes = Size::whereIn('id', $sizeIds)
            ->whereNotIn('id', DB::table('product_variants')->select('size_id'))
            ->delete();

        $this->info("Silindi: {$deletable->count()} məhsul, {$files} şəkil faylı, {$emptySizes} boş '{$name}' ölçüsü.");

        return self::SUCCESS;
    }
}
