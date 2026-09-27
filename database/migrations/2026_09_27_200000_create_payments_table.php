<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // customers.id is an INT in this project, not BIGINT.
            $table->integer('customer_id');
            $table->unsignedBigInteger('order_id');
            $table->enum('provider', ['birbank', 'm10']);
            $table->string('provider_order_id', 191)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('session_id', 255)->nullable();
            $table->string('card_pan', 32)->nullable();
            $table->text('response_text')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->unique(['provider', 'provider_order_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
