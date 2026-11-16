<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * İstehsal cədvəllərinə ortaq "production_" prefiksi (məlumat olduğu kimi qalır, yalnız ad dəyişir).
 * Bağlantı sütunlarının adları (raw_material_id, own_product_id, material_purchase_id) dəyişmir.
 */
return new class extends Migration
{
    private const NAMES = [
        'raw_materials' => 'production_materials',
        'raw_material_movements' => 'production_material_movements',
        'material_purchases' => 'production_material_purchases',
        'own_products' => 'production_products',
        'own_product_recipe_items' => 'production_recipe_items',
    ];

    public function up(): void
    {
        foreach (self::NAMES as $old => $new) {
            if (Schema::hasTable($old) && !Schema::hasTable($new)) Schema::rename($old, $new);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $old => $new) {
            if (Schema::hasTable($new) && !Schema::hasTable($old)) Schema::rename($new, $old);
        }
    }
};
