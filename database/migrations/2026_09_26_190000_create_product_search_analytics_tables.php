<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 100);
            $table->string('normalized_query', 100);
            $table->string('visitor_id', 64);
            // customers.id layihədə integer-dir.
            $table->integer('user_id')->nullable();
            $table->unsignedSmallInteger('result_count')->default(0);
            $table->json('matched_product_ids')->nullable();
            $table->timestamp('searched_at');

            $table->index(['normalized_query', 'searched_at']);
            $table->index(['visitor_id', 'searched_at']);
            $table->index(['user_id', 'searched_at']);
        });

        Schema::create('product_search_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('search_log_id');
            $table->integer('product_id');
            $table->unsignedSmallInteger('result_rank');
            $table->timestamp('clicked_at');

            $table->foreign('search_log_id')
                ->references('id')
                ->on('product_search_logs')
                ->cascadeOnDelete();
            $table->index(['product_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_clicks');
        Schema::dropIfExists('product_search_logs');
    }
};
