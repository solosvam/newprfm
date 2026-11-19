<?php

namespace App\Services\Messenger;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Səsli mesaja səsli cavab (OpenAI): səs → mətn (transcribe), mətn → cavab (Responses API), cavab → səs (TTS, OGG/Opus).
 * Modellər: services.openai.transcribe_model / model / tts_model, səs — tts_voice.
 */
class VoiceAssistant
{
    private const API = 'https://api.openai.com/v1/';

    public function transcribe(string $audio): string
    {
        $response = $this->http(60)
            ->attach('file', $audio, 'voice.ogg')
            ->post(self::API.'audio/transcriptions', [
                'model' => config('services.openai.transcribe_model'),
                'language' => 'az',
                // Tanımağa ipucu — mağazanın sözləri
                'prompt' => 'ParfumShop ətir mağazası: ətir, parfüm, yay, qış, qadın, kişi, uniseks, notlar, sitrus, vanil, ənbər, Chanel, Dior, Tom Ford, Creed.',
            ]);

        return trim((string) $this->ok($response)->json('text'));
    }

    /**
     * @param list<array{role: string, text: string}> $history əvvəlki növbələr (köhnədən yeniyə)
     */
    public function reply(string $customerName, array $history, string $text): string
    {
        $input = [];
        foreach ($history as $turn) {
            $input[] = ['role' => $turn['role'], 'content' => $turn['text']];
        }
        $input[] = ['role' => 'user', 'content' => $text];

        $response = $this->http(60)->post(self::API.'responses', [
            'model' => config('services.openai.model'),
            'reasoning' => ['effort' => 'minimal'],   // səsli söhbətdə sürət vacibdir
            'instructions' => $this->instructions($customerName),
            'input' => $input,
        ]);
        foreach ($this->ok($response)->json('output') ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && trim((string) ($content['text'] ?? '')) !== '') {
                    return trim($content['text']);
                }
            }
        }

        throw new RuntimeException('OpenAI cavabında mətn tapılmadı.');
    }

    /** Telegram səsli mesajı üçün OGG/Opus */
    public function speak(string $text): string
    {
        $response = $this->http(60, false)->post(self::API.'audio/speech', [
            'model' => config('services.openai.tts_model'),
            'voice' => config('services.openai.tts_voice'),
            'input' => $text,
            'instructions' => 'Azərbaycan dilində, təbii, mehriban və aydın danış — mağaza məsləhətçisi kimi. Sürət orta.',
            'response_format' => 'opus',
        ]);

        return $this->ok($response)->body();
    }

    private function instructions(string $customerName): string
    {
        return <<<TXT
Sən ParfumShop.az ətir mağazasının səsli köməkçisisən. Müştərinin adı: {$customerName}.
Cavabın səsə çevrilib səsli mesaj kimi göndəriləcək, ona görə:
- Azərbaycan dilində, müştəriyə "siz" deyə müraciət et.
- Çox qısa cavab ver: ən çox 3 qısa cümlə (səsli mesaj 20 saniyədən uzun olmasın). Ən çox 2 ətir adı çək.
- Siyahı, emoji, link, ulduz, mötərizə, qısaltma yazma; rəqəmləri sözlə oxunacaq kimi yaz.
- Ətir seçimi, ətir notları, istifadə qaydası kimi suallarda kömək et.
- Qiymət, stok, endirim, sifarişin statusu və çatdırılma barədə məlumatın yoxdur — uydurma; saytda baxmağı və ya operatorla əlaqə saxlamağı tövsiyə et.
- Ətirlə əlaqəsi olmayan mövzularda nəzakətlə yalnız ətir mövzusunda kömək etdiyini de.
TXT;
    }

    private function http(int $timeout, bool $json = true): \Illuminate\Http\Client\PendingRequest
    {
        $key = (string) config('services.openai.api_key');
        if ($key === '') {
            throw new RuntimeException('OPENAI_API_KEY yazılmayıb.');
        }

        $request = Http::timeout($timeout)->withToken($key);

        return $json ? $request->acceptJson() : $request;
    }

    private function ok(Response $response): Response
    {
        if (!$response->successful()) {
            throw new RuntimeException('OpenAI: '.data_get($response->json(), 'error.message', 'HTTP '.$response->status()));
        }

        return $response;
    }
}
