<?php

namespace App\Services\Search;

/**
 * Müştəri mesajından axtarış sorğusu: "Salam, Dior Savaj 100 lük neçəyədi?" → sorğu + ölçü 100.
 * Burada yalnız ölçü/rəqəm ayrılır. Artıq sözlər (salam, neçəyədi, göndər…, Naxçıvan…) lüğətdədir —
 * "Axtarış idarəetməsi" → "Artıq söz" (tam söz və ya kök); ProductSearchService onları atır.
 */
class ProductQueryParser
{
    /**
     * @return array{query: string, size: ?string}
     */
    public static function parse(string $text): array
    {
        $tokens = explode(' ', ProductSearchNormalizer::normalize(mb_substr($text, 0, 300)));
        $size = null;
        $words = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            // "100", "100ml", "100mlsi", "50luk", "50lik" — ölçü
            if (preg_match('/^(\d{1,3})(?:ml[a-z]*|luk|luq|lik|lek|liy[a-z]*|luy[a-z]*)?$/', $token, $m)) {
                if ($size === null && (int) $m[1] >= 5) {
                    $size = $m[1];
                }
                // təkcə rəqəm sorğuda qalır: "212 VIP" adın hissəsidir; adda yoxdursa axtarış rəqəmsiz təkrarlanır
                if (ctype_digit($token)) {
                    $words[] = $token;
                }
                continue;
            }
            $words[] = $token;
        }

        return ['query' => implode(' ', $words), 'size' => $size];
    }
}
