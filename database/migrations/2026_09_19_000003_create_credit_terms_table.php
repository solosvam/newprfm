<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_terms', function (Blueprint $table) {
            $table->id();
            $table->longText('content_az')->nullable();
            $table->longText('content_en')->nullable();
            $table->longText('content_ru')->nullable();
            $table->timestamps();
        });

        DB::table('credit_periods')->insert([
            ['month' => 3, 'interest_rate' => 0, 'active' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['month' => 6, 'interest_rate' => 17.6, 'active' => 1, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['month' => 9, 'interest_rate' => 25, 'active' => 1, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['month' => 12, 'interest_rate' => 33.3, 'active' => 1, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['month' => 15, 'interest_rate' => 37, 'active' => 1, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['month' => 18, 'interest_rate' => 44.9, 'active' => 1, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_terms');
    }
};
