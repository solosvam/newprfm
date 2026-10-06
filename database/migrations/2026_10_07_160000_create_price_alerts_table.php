<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Qiymət enəndə xəbər ver": müştəri məhsulun seçilmiş ölçüsünə abunə olur (abunə anındakı qiymətlə).
 * Variantın qiyməti bu qiymətdən aşağı düşəndə push gedir (NotifyPriceDrop) və notified_at yazılır — təkrar getmir.
 */
return new class extends Migration {
    public function up(): void
    {
        // customers.id və product_variants.id — signed INT (köhnə sxem)
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id');
            $table->integer('product_variant_id');
            $table->decimal('price', 10, 2);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'product_variant_id']);
            $table->index(['product_variant_id', 'notified_at']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
