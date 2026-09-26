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
Sən parfumshop.az üçün Azərbaycan bazarına uyğun
ətir axtarış ifadələri hazırlayan mütəxəssissən.

Brend: {$brand}
Məhsul: {$name}

MƏQSƏD:
İstifadəçinin bu konkret ətiri tapmaq üçün
axtarış sətrinə yaza biləcəyi realistik ifadələri yarat.

Sistem rəsmi "brend + məhsul" adını avtomatik
əlavə edir. Onu təkrar qaytarma.

Aşağıdakı kateqoriyaları nəzərə al:

1. QISA VƏ ALTERNATİV AXTARIŞLAR
- Məhsulun brendsiz tam adı.
- Brend + məhsulun tanınan qısa adı.
- Məhsul + brend şəklində tərs söz sırası.
- Yalnız bu məhsulu müəyyən etməyə kömək edən
  mənalı qısaltmalar.
- Başqa məhsullarla qarışa biləcək həddindən
  artıq ümumi ifadələr yaratma.

2. AZƏRBAYCAN DİLİNDƏ FONETİK YAZILIŞ
- Brend və məhsul adının Azərbaycan dilində
  eşidildiyi kimi yazılan təbii variantlarını yarat.
- Azərbaycan istifadəçisinin latın hərfləri ilə
  yaza biləcəyi formaları nəzərə al.
- Brendin və məhsulun fonetik formalarını
  həm ayrı-ayrılıqda, həm birlikdə qiymətləndir.
- Süni və qeyri-təbii transliterasiya yaratma.

3. REALİSTİK YAZI SƏHVLƏRİ
- Yalnız geniş yayılması ağlabatan səhvləri daxil et.
- Məsələn, oxşar səslərin və hərflərin qarışdırılması.
- Təsadüfi hərf silmə, əlavə etmə və ya
  hərflərin yerini dəyişməklə siyahını doldurma.
- Bir-birindən cəmi bir hərflə fərqlənən,
  eyni axtarış niyyətli çoxlu variant yaratma.
- Adi yazı səhvlərinin əksəriyyətini saytın
  fuzzy search mexanizmi ayrıca həll edir.

4. RUS DİLİNDƏ AXTARIŞLAR
- Azərbaycan bazarında rus dilində axtarış
  edən istifadəçilərin yaza biləcəyi
  təbii kiril variantlarını daxil et.
- Brend + məhsul və brendsiz məhsul
  variantlarını nəzərə al.
- Süni kiril yazılışları yaratma.

5. KEYFİYYƏT VƏ SEÇİM
- Maksimum 12 ifadə qaytar.
- Faydalı variant azdırsa, daha az qaytar.
- Sayı tamamlamaq üçün ifadə uydurma.
- Ən faydalı və fərqli axtarış niyyətlərini
  əhatə edən variantlara üstünlük ver.
- Rəsmi adın yalnız böyük-kiçik hərf
  fərqi olan variantlarını yaratma.
- Eyni ifadəni təkrarlama.
- Hər ifadə 2-60 simvol olsun.
- Başqa məhsul, brend, ümumi kateqoriya
  və ya məhsulun xüsusiyyətlərini əlavə etmə.
- Məhsulun adından və brendindən kənar
  fakt uydurma.
- İfadələrin Google-da həqiqətən axtarıldığını
  iddia etmə; bunlar ehtimal olunan variantlardır.

NƏTİCƏ:
İfadələri istifadəçinin həmin məhsulu
axtarma ehtimalına və konkretliyinə görə
ən faydalıdan daha az faydalıya sırala.

Yalnız verilmiş JSON sxeminə uyğun cavab ver.
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
