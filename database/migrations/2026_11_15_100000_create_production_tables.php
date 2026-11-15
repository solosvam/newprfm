<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Öz istehsalımız:
 *  - xammal (raw_materials) və onun hər hərəkəti (raw_material_movements: alış +, istehsala sərf −) — qalıq = cəm, maya orta;
 *  - xammal alışı (material_purchases) — tədarükçü finance_accounts.type = supplier; ödəniş "supplier_payment" (kateqoriya "Xammal"),
 *    ödənilməyən hissə tədarükçüyə borcdur;
 *  - öz məhsullarımız (own_products — kataloqdakı məhsulun ölçüsü), resepti (own_product_recipe_items), istehsal partiyaları;
 *  - hazır mal "Öz anbarımız"dadır (warehouses.is_own — 2026_11_15_090000): sifariş oradan götürülür, qalıq = istehsal − aktiv götürülənlər.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('unit', 10); // ml | g | pcs
            $table->decimal('avg_cost', 14, 4)->default(0); // vahidin orta mayası
            $table->string('note', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('material_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchased_at');
            $table->unsignedBigInteger('supplier_account_id')->nullable(); // finance_accounts (type = supplier)
            $table->string('payment', 10);                                 // paid | debt | opening
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('supplier_account_id');
        });

        Schema::create('own_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->unique();
            $table->decimal('avg_cost', 14, 4)->default(0); // bir şüşənin orta mayası
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('own_product_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3); // bir şüşəyə
            $table->unique(['own_product_id', 'raw_material_id']);
        });

        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('own_product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->date('produced_at');
            $table->decimal('materials_cost', 12, 2)->default(0);
            $table->decimal('extra_cost', 12, 2)->default(0);
            $table->decimal('unit_cost', 14, 4);
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('raw_material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);  // + alış, − istehsala sərf
            $table->decimal('unit_cost', 14, 4); // alışda — alış qiyməti, sərfdə — o anki orta maya
            $table->string('kind', 15);          // purchase | production
            $table->foreignId('material_purchase_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('production_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_material_movements');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('own_product_recipe_items');
        Schema::dropIfExists('own_products');
        Schema::dropIfExists('material_purchases');
        Schema::dropIfExists('raw_materials');
    }
};
