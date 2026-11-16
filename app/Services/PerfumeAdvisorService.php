<?php

namespace App\Services;

use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AI ətir məsləhətçisi (/advisor) — chat:
 *  - chat(): AI söhbəti oxuyub növbəti sualı + 2–5 hazır cavab yazır və o vaxta qədər bilinənləri (profil) qaytarır.
 *    İlk iki mesaj (salam + "kimin üçündür?") səhifədə hazırdır — AI sorğusu yoxdur. Ən çox MAX_ANSWERS cavabdan sonra
 *    AI söhbəti bitirir (done).
 *  - recommend(): profilə görə kataloqdan namizədlər (cins, büdcə — kod süzür; ən çox satılanlar öndə),
 *    AI onlardan ən uyğun 6-nı seçib qısa izah yazır. Eyni profil keşdə saxlanılır.
 * Söhbət serverdə saxlanmır: brauzer (localStorage) hər addımda bütün söhbəti göndərir.
 * Təhqir: AI son mesajın təhqir olduğunu bildirir (abusive) — cavabı AdvisorController verir (xəbərdarlıq / dayandırma / Telegram).
 * Notlar kataloqda azdır — model ətirləri brend+ad ilə öz biliyindən tanıyır.
 */
class PerfumeAdvisorService
{
    public const MAX_CANDIDATES = 250;
    public const PICKS = 6;
    /** Müştərinin ən çox neçə cavabından sonra söhbət bitir (ilk "kimin üçündür?" daxil) */
    public const MAX_ANSWERS = 6;
    private const CACHE_TTL = 21600;   // 6 saat

    public const FOR = ['women', 'men', 'any'];
    public const STRENGTH = ['any', 'light', 'medium', 'strong'];

    /** cins => genders.id (1 kişi, 2 qadın, 3 unisex) */
    private const GENDERS = ['women' => [2, 3], 'men' => [1, 3], 'any' => [1, 2, 3]];

    private const LANGUAGES = ['az' => 'Azerbaijani', 'en' => 'English', 'ru' => 'Russian'];

    public function __construct(private OpenAiPerfumeService $openAi)
    {
    }

    /**
     * Söhbətin növbəti addımı.
     *
     * @param  list<array{role: string, text: string}>  $messages
     * @return array{reply: string, quick_replies: list<string>, done: bool, abusive: bool, profile: array}
     */
    public function chat(array $messages, string $locale): array
    {
        $answers = count(array_filter($messages, fn ($m) => $m['role'] === 'user'));
        $mustFinish = $answers >= self::MAX_ANSWERS;
        $language = self::LANGUAGES[$locale] ?? 'Azerbaijani';
        $dialog = collect($messages)->map(fn ($m) => ($m['role'] === 'user' ? 'Customer: ' : 'Advisor: ').$m['text'])->implode("\n");

        $prompt = "You are a friendly perfume advisor chatting with a customer of Parfumshop.az (online perfume store in Azerbaijan).\n"
            ."Your goal: learn the customer's taste with short questions, one question per message, then a catalog search picks perfumes for them.\n"
            ."Write ONLY in {$language}. Be warm and brief (max 2 short sentences). Address the customer politely.\n"
            ."Things worth knowing (skip what the customer already said, combine when natural, follow up on interesting answers):\n"
            ."who it is for (women / men / either), occasion (daily, evening, office, gift…), season or climate, scents they like or dislike, "
            ."how noticeable / long-lasting it should be, budget in AZN, perfumes they already like.\n"
            ."Before finishing always learn the budget and ask about scents they like (or a perfume they already love), unless the customer refuses or already said it.\n"
            ."Do not recommend specific perfumes yourself — the search does that. The closing sentence must not promise any time (no \"minutes\").\n"
            ."If the customer writes about something unrelated, gently bring the conversation back to choosing a perfume.\n"
            ."abusive: true ONLY if the customer's LAST message insults, swears or is obscene (any language, including Azerbaijani, Russian and Turkish slang "
            ."and misspellings — e.g. siktir, sik-, gijdıllaq/gicdıllaq, qəhbə, oğraş, peysər, blyat, nahuy, amk). Earlier messages do not count. "
            ."Never apologize to insults and never answer them with \"I understand\".\n"
            ."quick_replies: 2–5 short answer suggestions (max 30 characters each) the customer can tap for YOUR question; empty when done.\n"
            ."profile: everything known so far (for = women|men|any|unknown; budget_min / budget_max in AZN or null; strength = any|light|medium|strong; "
            ."other fields as short English text, families as a list of English scent words; notes = any other useful detail in English).\n"
            .($mustFinish
                ? "The conversation must END NOW: set done=true, quick_replies=[], reply with one short sentence that you are picking perfumes.\n"
                : "Set done=true only when you know enough (at least who it is for and the budget) — then reply with one short sentence that you are picking perfumes. "
                    ."Otherwise done=false and ask the next question. The customer has answered {$answers} of at most ".self::MAX_ANSWERS." questions.\n")
            ."\nConversation so far:\n".$dialog;

        $data = $this->openAi->json($prompt, self::CHAT_SCHEMA);
        $profile = $this->profile($data['profile'] ?? []);

        return [
            'reply' => trim((string) ($data['reply'] ?? '')),
            'quick_replies' => $mustFinish ? [] : collect($data['quick_replies'] ?? [])
                ->map(fn ($r) => mb_substr(trim((string) $r), 0, 40))->filter()->unique()->take(5)->values()->all(),
            'done' => !($data['abusive'] ?? false) && ($mustFinish || (bool) ($data['done'] ?? false)),
            'abusive' => (bool) ($data['abusive'] ?? false),
            'profile' => $profile,
        ];
    }

    /**
     * @return array{intro: string, items: Collection<int, array{product: Product, reason: string}>}
     */
    public function recommend(array $profile, string $locale): array
    {
        $profile = $this->profile($profile);
        $candidates = $this->candidates($profile);
        if ($candidates->isEmpty()) {
            return ['intro' => '', 'items' => collect()];
        }

        $key = 'advisor:v2:'.md5(json_encode([$profile, $locale, $candidates->pluck('id')->all()]));
        $picks = Cache::remember($key, self::CACHE_TTL, fn () => $this->ask($profile, $locale, $candidates));

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
    public function candidates(array $profile): Collection
    {
        $profile = $this->profile($profile);
        $min = $profile['budget_min'] ?? 0;
        $max = $profile['budget_max'];
        $sold = DB::table('order_items')->selectRaw('COALESCE(SUM(quantity), 0)')->whereColumn('order_items.product_id', 'products.id');

        return app(CatalogService::class)->productQuery()
            ->with('ingredients')
            ->whereHas('genders', fn ($q) => $q->whereIn('genders.id', self::GENDERS[$profile['for']]))
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

    /** AI-nin profilini yoxlanmış formaya salır (bilinməyən cins — "any", büdcə — müsbət rəqəm, min ≤ max) */
    public function profile(array $raw): array
    {
        $money = fn ($v) => is_numeric($v) && (float) $v > 0 ? round((float) $v) : null;
        $text = fn ($v, int $max = 200) => mb_substr(trim(strip_tags((string) $v)), 0, $max);
        $min = $money($raw['budget_min'] ?? null);
        $max = $money($raw['budget_max'] ?? null);
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'for' => in_array($raw['for'] ?? null, self::FOR, true) ? $raw['for'] : 'any',
            'budget_min' => $min,
            'budget_max' => $max,
            'occasion' => $text($raw['occasion'] ?? ''),
            'season' => $text($raw['season'] ?? ''),
            'families' => collect(is_array($raw['families'] ?? null) ? $raw['families'] : [])
                ->map(fn ($f) => $text($f, 40))->filter()->unique()->take(8)->values()->all(),
            'strength' => in_array($raw['strength'] ?? null, self::STRENGTH, true) ? $raw['strength'] : 'any',
            'liked' => $text($raw['liked'] ?? ''),
            'notes' => $text($raw['notes'] ?? '', 400),
        ];
    }

    private function ask(array $profile, string $locale, Collection $candidates): array
    {
        $language = self::LANGUAGES[$locale] ?? 'Azerbaijani';
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
            ."Customer profile from the conversation (JSON):\n".json_encode($profile, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Meaning: for=women|men|any; budget in AZN (null = no limit); strength=light|medium|strong (longevity/projection); "
            ."families=preferred scent families; liked=perfumes the customer already likes; notes=other wishes or dislikes.\n\n"
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

    private const CHAT_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['reply', 'quick_replies', 'done', 'abusive', 'profile'],
        'properties' => [
            'reply' => ['type' => 'string'],
            'abusive' => ['type' => 'boolean'],
            'quick_replies' => ['type' => 'array', 'items' => ['type' => 'string']],
            'done' => ['type' => 'boolean'],
            'profile' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['for', 'budget_min', 'budget_max', 'occasion', 'season', 'families', 'strength', 'liked', 'notes'],
                'properties' => [
                    'for' => ['type' => 'string', 'enum' => ['women', 'men', 'any', 'unknown']],
                    'budget_min' => ['type' => ['number', 'null']],
                    'budget_max' => ['type' => ['number', 'null']],
                    'occasion' => ['type' => 'string'],
                    'season' => ['type' => 'string'],
                    'families' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'strength' => ['type' => 'string', 'enum' => ['any', 'light', 'medium', 'strong']],
                    'liked' => ['type' => 'string'],
                    'notes' => ['type' => 'string'],
                ],
            ],
        ],
    ];
}
