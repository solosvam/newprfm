<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kataloq cədvəllərində əlaqə sütunlarının indeksi yox idi (yalnız PRIMARY) — məhsul siyahısı, kateqoriya / brend
 * səhifələri, filtrlər və eager loading hər dəfə bütün cədvəli oxuyurdu. Mövcud indeks varsa təkrar yaradılmır.
 */
return new class extends Migration
{
    private const INDEXES = [
        'product_images' => ['product_images_product_sort_index' => ['product_id', 'sort_order']],
        'product_variants' => ['product_variants_product_active_index' => ['product_id', 'active'], 'product_variants_size_index' => ['size_id']],
        'product_categories' => ['product_categories_product_category_index' => ['product_id', 'category_id'], 'product_categories_category_product_index' => ['category_id', 'product_id']],
        'product_genders' => ['product_genders_product_gender_index' => ['product_id', 'gender_id'], 'product_genders_gender_product_index' => ['gender_id', 'product_id']],
        'products' => ['products_active_brand_index' => ['active', 'brand_id'], 'products_brand_index' => ['brand_id'], 'products_type_index' => ['type_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (!Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
