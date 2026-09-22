<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiPerfumeService
{
    public function generateFromFragrantica(string $url): array
    {
        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            throw new RuntimeException('OPENAI_API_KEY .env faylında təyin edilməyib.');
        }

        $response = Http::timeout(90)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model'),
                'tools' => [
                    ['type' => 'web_search'],
                ],
                'input' => $this->prompt($url),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'perfume_product_content',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $message = data_get($response->json(), 'error.message', 'OpenAI sorğusu uğursuz oldu.');
            throw new RuntimeException($message);
        }

        $json = $this->outputText($response->json());
        $data = json_decode($json, true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('OpenAI gözlənilən JSON cavabını qaytarmadı.');
        }

        return $data;
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
- description_az, description_en və description_ru hərəsi 80-120 söz olsun. Satış dilində, təbii, təkrarsız yaz. Fragrantica adını və istifadəçi rəylərini qeyd etmə.
- Məhsulun davamlılığı, yayılması və mövsümü barədə təsdiqlənmiş məlumat yoxdursa qəti iddia yazma.
- Yalnız göstərilən JSON sxeminə uyğun cavab ver.
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
}
