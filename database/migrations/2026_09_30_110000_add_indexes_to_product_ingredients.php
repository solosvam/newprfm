<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_ingredients köhnə cədvəldir — yalnız PRIMARY(id) var idi.
 * Tövsiyə (ProductRecommendationService) tərkib hissəsinə görə axtarır, məhsul səhifəsi isə məhsula görə.
 * Unique deyil: köhnə məlumatda təkrarlanan sətir ola bilər.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_ingredients', function (Blueprint $table) {
            if (!Schema::hasIndex('product_ingredients', 'product_ingredients_ingredient_product_index')) {
                $table->index(['ingredient_id', 'product_id'], 'product_ingredients_ingredient_product_index');
            }
            if (!Schema::hasIndex('product_ingredients', 'product_ingredients_product_ingredient_index')) {
                $table->index(['product_id', 'ingredient_id'], 'product_ingredients_product_ingredient_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_ingredients', function (Blueprint $table) {
            $table->dropIndex('product_ingredients_ingredient_product_index');
            $table->dropIndex('product_ingredients_product_ingredient_index');
        });
    }
};
