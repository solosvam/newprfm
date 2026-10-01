<?php

use App\Services\Search\ProductSearchNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Artıq söz" (type = ignore) iki rejimdə: tam söz (exact) və kök (prefix: "göndər" → göndərirsiniz, göndərin…).
 * Kodda sabit yazılmış siyahılar (ProductQueryParser::FILLER/FILLER_STEMS, ProductSearchService::QUERY_STOP_WORDS)
 * lüğətə köçürülür — bundan sonra admin paneldən idarə olunur, panel və sayt axtarışı eyni siyahını işlədir.
 */
return new class extends Migration {
    private const EXACT = [
        'salam', 'salamlar', 'sagolun', 'zehmet', 'olmasa', 'xahis', 'edirem', 'olar', 'olarmi', 'nedir', 'ne', 'qeder',
        'var', 'varmi', 'sizde', 'mene', 'bize', 'mehz', 'original', 'zavod', 'deyin', 'deyerdiniz', 'bilersiniz',
        'eau', 'de', 'du', 'la', 'le', 'toilette', 'cologne', 'edp', 'edt', 'edc', 'spray',
        'parfum', 'parfums', 'perfume', 'perfumes', 'luk', 'lik', 'lek', 'manat', 'azn',
        'paris', 'london', 'milano', 'italia',
    ];

    private const PREFIX = [
        'etir', 'etr', 'ml', 'qiymet', 'nece', 'lazim', 'qablasdir', 'orijinal',
        'olan', 'gonder', 'catdir', 'kuryer',
        'naxcivan', 'baki', 'gence', 'sumqayit', 'lenkeran', 'mingecevir', 'sirvan', 'quba', 'qebele', 'zaqatala',
        'region', 'rayon', 'seher',
    ];

    public function up(): void
    {
        Schema::table('search_aliases', function (Blueprint $table) {
            $table->string('match_type', 10)->default('exact')->after('type'); // exact | prefix (yalnız artıq sözlər üçün)
        });

        $now = now();
        $rows = [];
        foreach ([...array_map(fn ($word) => [$word, 'exact'], self::EXACT), ...array_map(fn ($word) => [$word, 'prefix'], self::PREFIX)] as [$word, $match]) {
            $rows[] = [
                'alias' => $word,
                'alias_normalized' => ProductSearchNormalizer::normalize($word),
                'type' => 'ignore',
                'match_type' => $match,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        // admin artıq eyni sözü alias kimi əlavə edibsə — toxunmuruq
        DB::table('search_aliases')->insertOrIgnore($rows);
        Cache::forget('product-search-vocabulary:v3');
    }

    public function down(): void
    {
        DB::table('search_aliases')->where('type', 'ignore')->whereNull('created_by')
            ->whereIn('alias_normalized', [...self::EXACT, ...self::PREFIX])->delete();
        Schema::table('search_aliases', function (Blueprint $table) {
            $table->dropColumn('match_type');
        });
        Cache::forget('product-search-vocabulary:v3');
    }
};
