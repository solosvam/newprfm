<?php

namespace App\Services\Messenger;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Səsli mesaja səsli cavab (OpenAI), iki üsul (services.telegram.customer_voice_mode):
 *  chain  — səs → mətn (transcribe), mətn → cavab (Responses API), cavab → səs (TTS, OGG/Opus);
 *  direct — audio model (chat/completions, audio_model) səsi dinləyib birbaşa səslə cavab verir; giriş wav (ffmpeg çevirir).
 * Hər iki üsulda model etir_axtar funksiyası ilə saytdakı qiymətləri götürür (ProductLookup).
 * Səs — tts_voice.
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

    private const MAX_TOOL_ROUNDS = 3;

    public function __construct(private ?ProductLookup $products = null)
    {
        $this->products ??= app(ProductLookup::class);
    }

    /**
     * Zəncir: mətn → cavab mətni (Responses API). Qiymət lazım olanda model etir_axtar funksiyasını çağırır.
     *
     * @param list<array{role: string, text: string}> $history əvvəlki növbələr (köhnədən yeniyə)
     * @return array{text: string, products: list<array>} products — funksiyanın tapdığı ətirlər (linklər üçün)
     */
    public function reply(string $customerName, array $history, string $text): array
    {
        $input = [];
        foreach ($history as $turn) {
            $input[] = ['role' => $turn['role'], 'content' => $turn['text']];
        }
        $input[] = ['role' => 'user', 'content' => $text];
        $found = [];
        $payload = [
            'model' => config('services.openai.model'),
            'reasoning' => ['effort' => 'minimal'],   // səsli söhbətdə sürət vacibdir
            'instructions' => $this->instructions($customerName),
            'tools' => [['type' => 'function', 'name' => ProductLookup::TOOL, 'description' => ProductLookup::TOOL_DESCRIPTION,
                'parameters' => ProductLookup::TOOL_PARAMETERS, 'strict' => true]],
            'input' => $input,
        ];

        for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->ok($this->http(60)->post(self::API.'responses', $payload));
            $calls = [];
            foreach ($response->json('output') ?? [] as $item) {
                if (($item['type'] ?? null) === 'function_call') {
                    $calls[] = $item;
                }
                foreach ($item['content'] ?? [] as $content) {
                    if (!$calls && ($content['type'] ?? null) === 'output_text' && trim((string) ($content['text'] ?? '')) !== '') {
                        return ['text' => trim($content['text']), 'products' => array_values($found)];
                    }
                }
            }
            if (!$calls || $round === self::MAX_TOOL_ROUNDS) {
                break;
            }
            // Funksiyanın nəticəsi əvvəlki cavabın davamı kimi (previous_response_id — modelin öz düşüncəsi də qalır)
            $payload['previous_response_id'] = $response->json('id');
            $payload['input'] = [];
            foreach ($calls as $call) {   // foreach — $found istinadla dolur (arrow function dəyərlə götürərdi)
                $payload['input'][] = ['type' => 'function_call_output', 'call_id' => $call['call_id'], 'output' => $this->runTool($call['arguments'] ?? '{}', $found)];
            }
        }

        throw new RuntimeException('OpenAI cavabında mətn tapılmadı.');
    }

    /**
     * Birbaşa: səs → səs. Əvvəlki növbələr mətn kimi verilir. Funksiya çağırılsa — nəticə ilə ikinci sorğu (səs onda gəlir).
     *
     * @param list<array{role: string, text: string}> $history
     * @return array{text: string, audio: string, products: list<array>} cavabın mətni, OGG/Opus səsi, tapılan ətirlər
     */
    public function talk(string $customerName, array $history, string $ogg, ?string $voice = null): array
    {
        $messages = [['role' => 'system', 'content' => $this->instructions($customerName)]];
        foreach ($history as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['text']];
        }
        $messages[] = ['role' => 'user', 'content' => [['type' => 'input_audio', 'input_audio' => ['data' => base64_encode($this->toWav($ogg)), 'format' => 'wav']]]];
        $found = [];

        for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->ok($this->http(90)->post(self::API.'chat/completions', [
                'model' => config('services.openai.audio_model'),
                'modalities' => ['text', 'audio'],
                'audio' => ['voice' => $voice ?: config('services.openai.tts_voice'), 'format' => 'opus'],
                'tools' => [['type' => 'function', 'function' => ['name' => ProductLookup::TOOL, 'description' => ProductLookup::TOOL_DESCRIPTION,
                    'parameters' => ProductLookup::TOOL_PARAMETERS]]],
                'messages' => $messages,
            ]));
            $message = $response->json('choices.0.message') ?? [];
            $calls = $message['tool_calls'] ?? [];
            if ($calls && $round < self::MAX_TOOL_ROUNDS) {
                $messages[] = ['role' => 'assistant', 'content' => $message['content'] ?? null, 'tool_calls' => $calls];
                foreach ($calls as $call) {
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => $this->runTool($call['function']['arguments'] ?? '{}', $found)];
                }
                continue;
            }
            $audio = base64_decode((string) ($message['audio']['data'] ?? ''));
            if ($audio === '') {
                throw new RuntimeException('OpenAI cavabında səs yoxdur.');
            }

            return ['text' => trim((string) ($message['audio']['transcript'] ?? '')), 'audio' => $audio, 'products' => array_values($found)];
        }

        throw new RuntimeException('OpenAI cavabında səs yoxdur.');
    }

    /** etir_axtar: tapılanlar $found-a (id üzrə) yığılır, modelə qısa JSON qayıdır */
    private function runTool(string $arguments, array &$found): string
    {
        $query = trim((string) (json_decode($arguments, true)['query'] ?? ''));
        $products = $query === '' ? [] : $this->products->find($query);
        foreach ($products as $product) {
            $found[$product['id']] = $product;
        }

        return ProductLookup::forModel($products);
    }

    /** Telegram-ın OGG/Opus səsi → wav (audio model ogg qəbul etmir) */
    public function toWav(string $ogg): string
    {
        $in = tempnam(sys_get_temp_dir(), 'voice').'.ogg';
        $out = substr($in, 0, -4).'.wav';
        file_put_contents($in, $ogg);
        try {
            $process = new Process([config('services.openai.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-y', '-i', $in, '-ac', '1', '-ar', '16000', $out]);
            $process->setTimeout(30)->run();
            if (!$process->isSuccessful() || !is_file($out)) {
                throw new RuntimeException('ffmpeg səsi çevirmədi: '.trim($process->getErrorOutput() ?: 'ffmpeg tapılmadı ('.config('services.openai.ffmpeg').')'));
            }

            return (string) file_get_contents($out);
        } finally {
            @unlink($in);
            @unlink($out);
            @unlink(substr($in, 0, -4));
        }
    }

    /** Telegram səsli mesajı üçün OGG/Opus */
    public function speak(string $text, ?string $voice = null): string
    {
        $response = $this->http(60, false)->post(self::API.'audio/speech', [
            'model' => config('services.openai.tts_model'),
            'voice' => $voice ?: config('services.openai.tts_voice'),
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
- Çox qısa cavab ver: ən çox 3 qısa cümlə (səsli mesaj 20 saniyədən uzun olmasın). Ən çox 2 ətir adı çək; qiymət deyəndə ən çox 2 ölçü.
- Siyahı, emoji, link, ulduz, mötərizə, qısaltma yazma; rəqəmləri sözlə oxunacaq kimi yaz.
- Ətir seçimi, ətir notları, istifadə qaydası kimi suallarda kömək et.
- Qiyməti YALNIZ etir_axtar funksiyasının qaytardığı rəqəmlərdən de; funksiyanı çağırmadan heç vaxt məbləğ demə. Endirimi yalnız nəticədə "endirimlə" yazılıbsa de.
- Funksiya ətiri tapmasa, "saytımızda tapmadım" de. Ətirin linkini biz ayrıca mesajla göndəririk — linki oxuma, "linki göndərdim" deyə bilərsən.
- Stok, sifarişin statusu və çatdırılma barədə məlumatın yoxdur — "anbarda var", "mövcuddur" demə; saytda baxmağı və ya operatoru tövsiyə et.
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
