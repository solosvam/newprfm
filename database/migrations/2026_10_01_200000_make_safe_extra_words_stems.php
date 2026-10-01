<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Uzun, ətir adında rast gəlinməyən artıq sözlər kök olur: "salam" → salamlar, salamm; "xahis" → xahiş edirəm…
 * Qısa sözlər (de, la, le, ne, var, eau, paris…) tam söz qalır — kök olsalar Delina, Layton, Legend kimi adları atardılar.
 */
return new class extends Migration {
    private const WORDS = ['salam', 'sagolun', 'zehmet', 'xahis', 'edirem', 'original', 'zavod', 'mehz', 'deyin',
        'deyerdiniz', 'bilersiniz', 'sizde', 'manat'];

    public function up(): void
    {
        DB::table('search_aliases')->where('type', 'ignore')->whereIn('alias_normalized', self::WORDS)
            ->update(['match_type' => 'prefix', 'updated_at' => now()]);
        Cache::forget('product-search-vocabulary:v3');
    }

    public function down(): void
    {
        DB::table('search_aliases')->where('type', 'ignore')->whereIn('alias_normalized', self::WORDS)
            ->update(['match_type' => 'exact', 'updated_at' => now()]);
        Cache::forget('product-search-vocabulary:v3');
    }
};
