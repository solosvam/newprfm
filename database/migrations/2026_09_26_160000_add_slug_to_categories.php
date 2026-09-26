<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });

        DB::table('categories')->orderBy('id')->chunkById(500, function ($categories) {
            foreach ($categories as $category) {
                $base = substr(Str::slug($category->name_az ?: $category->name_en ?: $category->name_ru ?: 'category') ?: 'category', 0, 235);
                $slug = $base;
                $suffix = 2;

                while (DB::table('categories')->where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $suffix++;
                }

                DB::table('categories')->where('id', $category->id)->update(['slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
