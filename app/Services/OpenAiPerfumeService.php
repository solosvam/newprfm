<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiPerfumeService
{
    public function generateFromFragrantica(string $url): array
    {
        return $this->request($this->prompt($url), $this->schema(), true);
    }

    public function factsFromFragrantica(string $url): array
    {
        return $this->request($this->factsPrompt($url), $this->factsSchema());
    }

    public function generateSearchTerms(string $brand, string $name): array
    {
        $data = $this->request(
            $this->searchTermsPrompt($brand, $name),
            $this->searchTermsSchema(),
            false,
            false
        );

        return $data['terms'];
    }

    private function request(
        string $prompt,
        array $schema,
        bool $sanitizeDescriptions = false,
        bool $allowWebSearch = true
    ): array
    {
        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            throw new RuntimeException('OPENAI_API_KEY .env faylında təyin edilməyib.');
        }

        $payload = [
            'model' => config('services.openai.model'),
            'reasoning' => [
                'effort' => 'low',
            ],
            'input' => $prompt,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'perfume_product_content',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        if ($allowWebSearch) {
            $payload['tools'] = [
                [
                    'type' => 'web_search',
                    'filters' => [
                        'allowed_domains' => ['fragrantica.com'],
                    ],
                ],
            ];
        }

        $response = Http::timeout(90)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', $payload);

        if (!$response->successful()) {
            $message = data_get($response->json(), 'error.message', 'OpenAI sorğusu uğursuz oldu.');
            throw new RuntimeException($message);
        }

        $json = $this->outputText($response->json());
        $data = json_decode($json, true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('OpenAI gözlənilən JSON cavabını qaytarmadı.');
        }

        return $sanitizeDescriptions ? $this->sanitizeDescriptions($data) : $data;
    }

    private function prompt(string $url): string
    {
        return <<<PROMPT
Sən parfumshop.az üçün dəqiq məhsul məlumatı hazırlayırsan.

Bu Fragrantica məhsul səhifəsini web axtarışından oxu:
{$url}

Qaydalar:
- Yalnız bu məhsula aid, mənbədə təsdiqlənən faktları qaytar.
- Məlumat tapılmırsa null qaytar, təxmin etmə.
- gender yalnız "women", "men" və ya "unisex" olsun.
- notes və accords yalnız qısa adlardan ibarət massiv olsun.
- source_description İngiliscə 2-4 cümləlik faktiki xülasə olsun; mənbəni sözbəsöz uzun köçürmə.
- description_az, description_en və description_ru hərəsi 80-120 söz olsun. Satış dilində, təbii, təkrarsız yaz.
- Bu üç description sahəsində Fragrantica, heç bir başqa sayt adı, URL, Markdown linki, citation, mənbə qeydi və ya "according to" tipli ifadə qətiyyən yazma.
- Məhsulun davamlılığı, yayılması və mövsümü barədə təsdiqlənmiş məlumat yoxdursa qəti iddia yazma.
- Yalnız göstərilən JSON sxeminə uyğun cavab ver.
PROMPT;
    }

    private function factsPrompt(string $url): string
    {
        return <<<PROMPT
Bu Fragrantica məhsul səhifəsini web axtarışından oxu və yalnız təsdiqlənən faktları qaytar:
{$url}

Qaydalar:
- name, brand, gender, year, perfumer, notes və accords bu konkret məhsula aid olsun.
- Məlumat tapılmırsa null, notes və accords üçün boş massiv qaytar; təxmin etmə.
- gender yalnız "women", "men" və ya "unisex" olsun.
- source_description İngiliscə qısa faktiki xülasə olsun; mənbə adı, URL və ya link yazma.
- Yalnız göstərilən JSON sxeminə uyğun cavab ver.
PROMPT;
    }

    private function searchTermsPrompt(string $brand, string $name): string
    {
        return <<<PROMPT
Sən parfumshop.az saytında məhsul axtarışı üçün yazılış variantları hazırlayırsan.

Brend: {$brand}
Məhsul: {$name}

4-14 fərqli axtarış ifadəsi qaytar. Sistem ayrıca rəsmi "brend + məhsul" adını əlavə edəcək.

Qaydalar:
- yalnız bu konkret məhsula aid ifadələr yaz; başqa məhsul, brend və ya ümumi "women", "perfume" kimi söz yazma.
- istifadəçinin yaza biləcəyi rəsmi yazılış, söz sırası, qısa forma, azərbaycanca eşidilən fonetik yazılış və real typo variantlarını daxil et.
- hər ifadə 2-60 simvol olsun, təkrarlanmasın.
- məhsulun brendi və ya adından fakt uydurma.
- yalnız JSON sxeminə uyğun cavab ver.
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'source_url', 'name', 'brand', 'gender', 'year', 'perfumer',
                'notes', 'accords', 'source_description', 'description_az',
                'description_en', 'description_ru',
            ],
            'properties' => [
                'source_url' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'brand' => ['type' => 'string'],
                'gender' => ['type' => ['string', 'null']],
                'year' => ['type' => ['integer', 'null']],
                'perfumer' => ['type' => ['string', 'null']],
                'notes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'accords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'source_description' => ['type' => ['string', 'null']],
                'description_az' => ['type' => 'string'],
                'description_en' => ['type' => 'string'],
                'description_ru' => ['type' => 'string'],
            ],
        ];
    }

    private function factsSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'source_url', 'name', 'brand', 'gender', 'year', 'perfumer',
                'notes', 'accords', 'source_description',
            ],
            'properties' => [
                'source_url' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'brand' => ['type' => 'string'],
                'gender' => ['type' => ['string', 'null']],
                'year' => ['type' => ['integer', 'null']],
                'perfumer' => ['type' => ['string', 'null']],
                'notes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'accords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'source_description' => ['type' => ['string', 'null']],
            ],
        ];
    }

    private function searchTermsSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['terms'],
            'properties' => [
                'terms' => [
                    'type' => 'array',
                    'minItems' => 4,
                    'maxItems' => 14,
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];
    }

    private function outputText(array $response): string
    {
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI cavabında mətn tapılmadı.');
    }

    private function sanitizeDescriptions(array $data): array
    {
        foreach (['description_az', 'description_en', 'description_ru'] as $field) {
            $description = (string) ($data[$field] ?? '');
            $description = preg_replace('/\s*\[[^\]]*\]\(https?:\/\/[^)]+\)/iu', '', $description);
            $description = preg_replace('/\s*https?:\/\/\S+/iu', '', $description);
            $description = preg_replace('/\s*cite[^]*/u', '', $description);
            $description = preg_replace('/\bfragrantica(?:\.com)?\b/iu', '', $description);
            $data[$field] = trim(preg_replace('/\s{2,}/u', ' ', $description));
        }

        return $data;
    }
}
