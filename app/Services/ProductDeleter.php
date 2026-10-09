<?php

namespace App\Services;

use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Məhsulu ona bağlı bütün məlumatlar və şəkil faylları ilə birlikdə silir.
 * Sifarişdə olan məhsul silinmir (tarixçə pozulmasın). Kredit müraciətləri sifarişə bağlıdır
 * (credit_applications.order_id), ona görə sifariş yoxlaması onları da əhatə edir.
 */
class ProductDeleter
{
    /** @var array<string, bool> */
    private array $tables = [];

    /** Silməyə mane olan səbəb; yoxdursa null */
    public function blockedReason(Product $product): ?string
    {
        $variantIds = $product->variants()->pluck('id');

        if ($this->hasTable('order_items') && DB::table('order_items')
            ->where('product_id', $product->id)
            ->orWhereIn('product_variant_id', $variantIds)
            ->exists()) {
            return 'sifarişdə var';
        }

        return null;
    }

    /** Silir və silinən şəkil fayllarının sayını qaytarır */
    public function delete(Product $product): int
    {
        $imageNames = DB::transaction(function () use ($product) {
            $variantIds = $product->variants()->pluck('id');
            $imageNames = $product->images()->pluck('image')->all();

            $product->categories()->detach();
            $product->genders()->detach();
            $product->ingredients()->detach();
            $product->reviews()->delete();

            foreach (['product_favorites', 'product_discounts', 'featured_products', 'product_search_clicks'] as $table) {
                if ($this->hasTable($table)) {
                    DB::table($table)->where('product_id', $product->id)->delete();
                }
            }

            foreach (['price_alerts', 'customer_cart_items'] as $table) {
                if ($this->hasTable($table) && $variantIds->isNotEmpty()) {
                    DB::table($table)->whereIn('product_variant_id', $variantIds)->delete();
                }
            }

            $product->variants()->delete();
            $product->images()->delete();
            $product->delete();

            return $imageNames;
        });

        $deleted = 0;

        foreach ($imageNames as $imageName) {
            $path = public_path('frontend/uploads/products/' . $imageName);

            if ($imageName !== '' && is_file($path) && unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }
}
