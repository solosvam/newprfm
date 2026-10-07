<?php

namespace App\Services;

use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AI ətir məsləhətçisi (/advisor): müştərinin cavablarına görə kataloqdan namizədlər seçilir
 * (cins, büdcə; ən çox satılanlar öndə), OpenAI onlardan ən uyğun 6-nı seçib qısa izah yazır.
 * Notlar kataloqda azdır — model ətirləri brend+ad ilə öz biliyindən tanıyır. Eyni cavablar keşdə saxlanılır.
 */
class PerfumeAdvisorService
{
    public const MAX_CANDIDATES = 250;
    public const PICKS = 6;
    private const CACHE_TTL = 21600;   // 6 saat

    /** Sual => icazəli cavablar (forma və validasiya ilə eyni) */
    public const OPTIONS = [
        'for' => ['women', 'men', 'any'],
        'occasion' => ['daily', 'evening', 'work', 'gift'],
        'season' => ['any', 'warm', 'cold'],
        'families' => ['floral', 'fruity', 'fresh', 'sweet', 'woody', 'oriental', 'musky', 'aquatic'],
        'strength' => ['any', 'light', 'medium', 'strong'],
        'budget' => ['any', 'b100', 'b200', 'b350', 'b350plus'],
    ];

    /** büdcə => [min, max] ₼ (ən ucuz ölçünün qiyməti) */
    private const BUDGETS = ['any' => [0, null], 'b100' => [0, 100], 'b200' => [100, 200], 'b350' => [200, 350], 'b350plus' => [350, null]];

    /** cins => genders.id (1 kişi, 2 qadın, 3 unisex) */
    private const GENDERS = ['women' => [2, 3], 'men' => [1, 3], 'any' => [1, 2, 3]];

    public function __construct(private OpenAiPerfumeService $openAi)
    {
    }

    /**
     * @return array{intro: string, items: Collection<int, array{product: Product, reason: string}>}
     */
    public function recommend(array $answers, string $locale): array
    {
        $candidates = $this->candidates($answers);
        if ($candidates->isEmpty()) {
            return ['intro' => '', 'items' => collect()];
        }

        $key = 'advisor:'.md5(json_encode([$answers, $locale, $candidates->pluck('id')->all()]));
        $picks = Cache::remember($key, self::CACHE_TTL, fn () => $this->ask($answers, $locale, $candidates));

        $byId = $candidates->keyBy('id');
        $items = collect($picks['picks'] ?? [])
            ->filter(fn ($pick) => $byId->has($pick['id'] ?? 0))
            ->unique('id')
            ->take(self::PICKS)
            ->map(fn ($pick) => ['product' => $byId[$pick['id']], 'reason' => trim((string) $pick['reason'])])
            ->values();

        return ['intro' => trim((string) ($picks['intro'] ?? '')), 'items' => $items];
    }

    /** Cins və büdcəyə uyğun aktiv məhsullar, ən çox satılanlar öndə */
    public function candidates(array $answers): Collection
    {
        [$min, $max] = self::BUDGETS[$answers['budget'] ?? 'any'] ?? self::BUDGETS['any'];
        $sold = DB::table('order_items')->selectRaw('COALESCE(SUM(quantity), 0)')->whereColumn('order_items.product_id', 'products.id');

        return app(CatalogService::class)->productQuery()
            ->with('ingredients')
            ->whereHas('genders', fn ($q) => $q->whereIn('genders.id', self::GENDERS[$answers['for'] ?? 'any'] ?? self::GENDERS['any']))
            ->whereHas('variants', function ($q) use ($min, $max) {
                $q->where('active', 1)->where('price', '>=', $min);
                if ($max !== null) {
                    $q->where('price', '<=', $max);
                }
            })
            ->orderByDesc($sold)
            ->orderByDesc('products.id')
            ->limit(self::MAX_CANDIDATES)
            ->get();
    }

    private function ask(array $answers, string $locale, Collection $candidates): array
    {
        $language = ['az' => 'Azerbaijani', 'en' => 'English', 'ru' => 'Russian'][$locale] ?? 'Azerbaijani';
        $lines = $candidates->map(function (Product $p) {
            $price = (float) $p->variants->min('price');
            $notes = $p->ingredients->pluck('name_en')->filter()->take(8)->implode(', ');
            $type = $p->type?->name_en;

            return $p->id.' | '.trim(($p->brand?->name ?? '').' '.$p->name).($type ? ' | '.$type : '').' | from '.round($price).' AZN'.($notes ? ' | notes: '.$notes : '');
        })->implode("\n");

        $prompt = "You are an expert perfume advisor for Parfumshop.az, an online perfume store in Azerbaijan.\n"
            ."Choose the ".self::PICKS." best matching perfumes for the customer ONLY from the catalog list below (use the numeric id).\n"
            ."Use your knowledge of these real perfumes (brand + name) — their notes, character, longevity and season — because catalog notes are often missing.\n"
            ."Prefer variety (different brands/styles) and well-known, well-reviewed fragrances. Never invent ids.\n"
            ."For each pick write one short, warm sentence (max 25 words) in {$language} explaining why it fits this customer (mention 1–2 key notes).\n"
            ."Also write a one-sentence intro in {$language} summarizing the customer's taste. Address the customer informally but politely.\n\n"
            ."Customer answers (JSON):\n".json_encode($answers, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Meaning: for=women|men|any; occasion=daily|evening(special evening)|work(office)|gift; season=any|warm(spring-summer)|cold(autumn-winter); "
            ."families=preferred scent families; strength=light|medium|strong(longevity/projection); liked=perfumes the customer already likes (optional).\n\n"
            ."Catalog (id | perfume | type | price | notes):\n".$lines;

        return $this->openAi->json($prompt, [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['intro', 'picks'],
            'properties' => [
                'intro' => ['type' => 'string'],
                'picks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'reason'],
                        'properties' => ['id' => ['type' => 'integer'], 'reason' => ['type' => 'string']],
                    ],
                ],
            ],
        ]);
    }
}
