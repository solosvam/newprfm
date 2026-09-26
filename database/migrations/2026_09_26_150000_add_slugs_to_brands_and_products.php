<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });

        $brandNames = [];
        DB::table('brands')->orderBy('id')->chunkById(500, function ($brands) use (&$brandNames) {
            foreach ($brands as $brand) {
                $base = Str::slug($brand->name) ?: 'brand';
                $slug = $this->uniqueSlug('brands', $base);
                DB::table('brands')->where('id', $brand->id)->update(['slug' => $slug]);
                $brandNames[$brand->id] = $brand->name;
            }
        });

        DB::table('products')->orderBy('id')->chunkById(500, function ($products) use (&$brandNames) {
            foreach ($products as $product) {
                $base = Str::slug(trim(($brandNames[$product->brand_id] ?? '') . ' ' . $product->name)) ?: 'product';
                $slug = $this->uniqueSlug('products', $base);
                DB::table('products')->where('id', $product->id)->update(['slug' => $slug]);
            }
        });
    }

    private function uniqueSlug(string $table, string $base): string
    {
        $base = substr($base, 0, 235);
        $slug = $base;
        $suffix = 2;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('slug'));
        Schema::table('brands', fn (Blueprint $table) => $table->dropColumn('slug'));
    }
};
