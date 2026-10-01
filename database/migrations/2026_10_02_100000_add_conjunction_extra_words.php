<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bağlayıcılar artıq söz kimi: "Nina var? Və qiyməti" — "və" (ve) axtarışa düşüb "Love in Paris"-i tapırdı.
 * "qiymət" kökü "qiymə" olur ki, səhv yazılış ("qiymer", "qiymeti") da atılsın.
 */
return new class extends Migration {
    private const EXACT = ['ve', 'ile', 'ya', 'yoxsa', 'hem', 'amma', 'bir', 'bu', 'o'];

    public function up(): void
    {
        $now = now();
        DB::table('search_aliases')->insertOrIgnore(array_map(fn ($word) => [
            'alias' => $word, 'alias_normalized' => $word, 'type' => 'ignore', 'match_type' => 'exact',
            'created_at' => $now, 'updated_at' => $now,
        ], self::EXACT));

        // "qiymet" kökü → "qiyme" (qiymer, qiymeti, qiymətini)
        if (!DB::table('search_aliases')->where('alias_normalized', 'qiyme')->exists()) {
            DB::table('search_aliases')->where('alias_normalized', 'qiymet')->where('type', 'ignore')
                ->update(['alias' => 'qiymə', 'alias_normalized' => 'qiyme', 'match_type' => 'prefix', 'updated_at' => $now]);
        }
        Cache::forget('product-search-vocabulary:v4');
    }

    public function down(): void
    {
        DB::table('search_aliases')->where('type', 'ignore')->whereNull('created_by')->whereIn('alias_normalized', self::EXACT)->delete();
        DB::table('search_aliases')->where('alias_normalized', 'qiyme')->where('type', 'ignore')
            ->update(['alias' => 'qiymet', 'alias_normalized' => 'qiymet']);
        Cache::forget('product-search-vocabulary:v4');
    }
};
