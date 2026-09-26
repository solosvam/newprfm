<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_search_terms', function (Blueprint $table) {
            $table->id();
            // products.id layihədə integer-dir; foreign key tipi də eyni saxlanılır.
            $table->integer('product_id');
            $table->string('term');
            $table->string('normalized_term');
            $table->string('phonetic_term')->nullable();
            $table->string('token_signature')->nullable();
            $table->string('source', 20)->default('canonical');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('active')->default(true);

            $table->unique(['product_id', 'normalized_term']);
            $table->index(['normalized_term', 'active']);
            $table->index(['phonetic_term', 'active']);
            $table->index(['token_signature', 'active']);
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_terms');
    }
};
