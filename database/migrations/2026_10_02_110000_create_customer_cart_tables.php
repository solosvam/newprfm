<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_cart_items', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['customer_id', 'product_variant_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        // Cavab itəndə və ya iki tab eyni səbəti göndərəndə bir dəfə birləşdirilir.
        Schema::create('customer_cart_imports', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id');
            $table->uuid('token');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['customer_id', 'token']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cart_imports');
        Schema::dropIfExists('customer_cart_items');
    }
};
