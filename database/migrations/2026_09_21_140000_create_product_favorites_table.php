<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_favorites', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id');
            $table->integer('product_id');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['customer_id', 'product_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_favorites');
    }
};
