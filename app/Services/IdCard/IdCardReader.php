<?php

namespace App\Services\IdCard;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerCreditProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdCardReader
{
    public function __construct(
        private GoogleVisionOcr $ocr,
        private IdCardParser $parser,
        private NameMatcher $matcher,
    ) {}

    public function read(Request $request, Customer $customer): JsonResponse
    {
        $ocr = $this->ocr;
        $parser = $this->parser;
        $matcher = $this->matcher;
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        // 5xx yox: Cloudflare 502/503/504 cavabını öz səhifəsi ilə əvəz edir, mesaj itir
        if (!$ocr->isConfigured()) {
            return response()->json(['message' => __('credit_ocr_unavailable')], 422);
        }

        try {
            $card = $parser->parse($ocr->text(file_get_contents($request->file('image')->getRealPath())));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(array_filter([
                'message' => __('credit_ocr_failed'),
                'debug' => config('app.debug') ? get_class($e).': '.$e->getMessage() : null, // yalnız APP_DEBUG=true
            ]), 422);
        }

        $fields = array_filter([
            'father_name' => $card['father_name'],
            'fin' => $card['fin'],
            'id_card_series' => in_array($card['series'], CustomerCreditProfile::ID_CARD_SERIES, true) ? $card['series'] : null,
            'id_card_number' => $card['number'],
        ]);

        return response()->json([
            'fields' => (object) $fields,
            'is_id_card' => $card['type'] !== null,
            'card_name' => trim(($card['name'] ?? '').' '.($card['surname'] ?? '')) ?: null,
            'name_match' => $matcher->matches($card['name'], $card['surname'], $customer->name, $customer->surname),
        ]);
    }
}
