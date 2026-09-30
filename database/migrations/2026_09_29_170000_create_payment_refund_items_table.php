<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Məhsula bağlı geri ödəniş: bankdakı qaytarma əməliyyatı (payment_operations)
 * hansı ödəniş sətrinə (payment_items → məhsul) və hansı ləğvə aiddir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_refund_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_operation_id');
            $table->unsignedBigInteger('payment_item_id');
            $table->unsignedBigInteger('order_item_cancellation_id')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->foreign('payment_operation_id')->references('id')->on('payment_operations')->restrictOnDelete();
            $table->foreign('payment_item_id')->references('id')->on('payment_items')->restrictOnDelete();
            $table->foreign('order_item_cancellation_id')->references('id')->on('order_item_cancellations')->nullOnDelete();
            $table->unique(['payment_operation_id', 'payment_item_id']);
        });

        Schema::table('order_item_cancellations', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_operation_id')->nullable()->after('refund_status');
            $table->timestamp('refunded_at')->nullable()->after('payment_operation_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_cancellations', function (Blueprint $table) {
            $table->dropColumn(['payment_operation_id', 'refunded_at']);
        });
        Schema::dropIfExists('payment_refund_items');
    }
};
