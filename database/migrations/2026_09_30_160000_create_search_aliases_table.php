<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Axtarış lüğəti: səhv yazılış / qısaltma → brend, model və ya "nəzərə alma".
 * Köhnə məhsul-əsaslı alias-lar (product_search_terms, oxşarlıq hesablaması) ləğv olunur.
 */
return new class extends Migration {
    public function up(): void
    {
        // brands.id köhnə cədvəldir — FK sütununun tipi onunla eyni olmalıdır
        [$big, $unsigned] = [false, false];
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('brands')) {
            $type = strtolower((string) (DB::selectOne("SHOW COLUMNS FROM brands WHERE Field = 'id'")->Type ?? ''));
            $big = str_starts_with($type, 'bigint');
            $unsigned = str_contains($type, 'unsigned');
        }

        Schema::create('search_aliases', function (Blueprint $table) use ($big, $unsigned) {
            $table->id();
            $table->string('alias', 100);                          // adminin yazdığı kimi: "Diyor", "YSL"
            $table->string('alias_normalized', 100)->unique();     // axtarış bununla: "diyor", "ysl"
            $table->string('type', 10);                            // brand | model | ignore
            $table->{$big ? 'bigInteger' : 'integer'}('brand_id', false, $unsigned)->nullable(); // brand: məcburi, model: istəyə bağlı
            $table->string('original', 150)->nullable();           // model: "Sauvage"
            $table->unsignedBigInteger('created_by')->nullable();  // users; köhnə ID tipləri fərqlidir
            $table->timestamps();

            $table->index('brand_id');
            if (Schema::hasTable('brands')) {
                $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
            }
        });

        Schema::dropIfExists('product_search_terms');
    }

    public function down(): void
    {
        Schema::dropIfExists('search_aliases');
    }
};
