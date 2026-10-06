<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Məhsul endirimi (admin → məhsul → "Endirim" tabı): faiz bütün ölçülərə aiddir, [starts_at, ends_at) aralığında aktivdir.
 * Endirimi hamı (qonaqlar da) görür.
 * Bir məhsulun endirim dövrləri üst-üstə düşmür (controller yoxlayır); keçmiş endirimlər tarixçə kimi qalır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_discounts', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id'); // products.id — signed INT (köhnə sxem)
            $table->decimal('percent', 5, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'starts_at', 'ends_at']);
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};
