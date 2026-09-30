<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Məhsul / miqdar üzrə ləğv. order_items.quantity sifariş edilən miqdar kimi qalır (tarixçə silinmir),
 * cancelled_quantity ləğv olunanı saxlayır; aktiv miqdar = quantity − cancelled_quantity.
 * Hər ləğv ayrıca qeyddir: səbəb, müştərinin razılığı, məbləğ, bonus düzəlişi, qaytarma vəziyyəti, operator.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('cancelled_quantity')->default(0)->after('quantity');
        });

        Schema::create('order_item_cancellations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 12, 2);          // yekundan çıxılan (promo payı nəzərə alınmış)
            $table->string('reason', 30);              // not_in_stock | customer_refused | other
            $table->text('note')->nullable();
            $table->boolean('customer_agreed')->default(false);
            $table->decimal('bonus_adjustment', 12, 2)->default(0); // geri alınan qazanılmış bonus
            // Onlayn ödənilibsə: pending → (3-cü addım) refunded; bonusla ödənilibsə: bonus; nağd/ödənilməyib: null
            $table->string('refund_status', 20)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->index(['order_id', 'refund_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_cancellations');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('cancelled_quantity');
        });
    }
};
