<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            // customers.id tipi köhnə bazadan gəldiyi üçün burada FK tipi fərz edilmir.
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('product_variant_id')->index();
            $table->unsignedBigInteger('credit_period_id')->index();
            $table->decimal('product_price', 12, 2);
            $table->decimal('interest_rate', 8, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('monthly', 12, 2);
            $table->string('status', 24)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};
