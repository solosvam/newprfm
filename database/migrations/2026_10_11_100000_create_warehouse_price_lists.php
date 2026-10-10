<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anbarların price listləri (Excel importu). Hər import ayrıca siyahıdır; anbarın cari siyahısı — sonuncusu.
 * Siyahının bütün sətirləri saxlanır: bizim varianta (məhsul + ölçü) bağlanan da, bağlanmayan da (axtarışla tapılır).
 * Operatorun təsdiqlədiyi uyğunluqlar ayrıca yadda qalır — növbəti importda eyni ad avtomatik bağlanır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouse_price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->json('mapping');                       // vərəq, başlanğıc sətir, ad / qiymət / brend sütunları
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('warehouse_price_lists')->cascadeOnDelete();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedInteger('row_no');
            $table->string('brand_raw')->nullable();       // fayldakı brend (qrup başlığı və ya sütun)
            $table->string('raw_name', 500);               // fayldakı ad, olduğu kimi
            $table->string('name_key', 500)->index();      // normallaşdırılmış ad: axtarış və yadda qalan uyğunluq üçün
            $table->unsignedBigInteger('brand_id')->nullable()->index();
            $table->string('kind', 12)->nullable();        // EDP | EDT | EDC | PARFUM | EXTRAIT
            $table->char('gender', 1)->nullable();         // L | M | U
            $table->decimal('volume_ml', 8, 2)->nullable();
            $table->boolean('tester')->default(false);
            $table->boolean('is_set')->default(false);
            $table->decimal('price', 10, 2);
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            $table->string('matched_by', 10)->nullable();  // auto | memory | manual
        });

        // Operatorun təsdiqlədiyi uyğunluq: bu anbarda bu ad = bizim bu variant
        Schema::create('warehouse_price_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name_key', 500);
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'name_key'], 'warehouse_price_matches_unique');
        });

        // Price listdəki brend adı = bizim brend (YSL → Yves Saint Laurent); bütün anbarlar üçün ortaqdır
        Schema::create('price_list_brand_matches', function (Blueprint $table) {
            $table->id();
            $table->string('brand_key')->unique();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_brand_matches');
        Schema::dropIfExists('warehouse_price_matches');
        Schema::dropIfExists('warehouse_price_items');
        Schema::dropIfExists('warehouse_price_lists');
    }
};
