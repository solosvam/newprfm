<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admində saxlanmış bonus şərtlərinə (bonus_terms_*) istifadə müddəti sətri əlavə olunur: "• :expiry"
 * (BonusService::expiryText ayarlardan cümlə qurur). Sətir "sifariş bonusu təhvildən sonra" sətrinin altına,
 * tapılmasa — mətnin sonuna yazılır. Artıq :expiry varsa toxunulmur.
 */
return new class extends Migration
{
    private const ANCHORS = [
        'bonus_terms_az' => 'Sifariş bonusu sifariş təhvil',
        'bonus_terms_en' => 'The order bonus is credited',
        'bonus_terms_ru' => 'Бонус за заказ начисляется',
    ];

    public function up(): void
    {
        foreach (self::ANCHORS as $key => $anchor) {
            $text = DB::table('settings')->where('key', $key)->value('value');
            if ($text === null || str_contains($text, ':expiry')) {
                continue;
            }
            $lines = preg_split('/\R/u', $text);
            $at = null;
            foreach ($lines as $i => $line) {
                if (str_contains($line, $anchor)) {
                    $at = $i;
                }
            }
            if ($at === null) {
                $lines[] = '• :expiry';
            } else {
                array_splice($lines, $at + 1, 0, ['• :expiry']);
            }
            DB::table('settings')->where('key', $key)->update(['value' => implode("\n", $lines), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::ANCHORS) as $key) {
            $text = DB::table('settings')->where('key', $key)->value('value');
            if ($text !== null) {
                DB::table('settings')->where('key', $key)->update(['value' => preg_replace('/\R?• :expiry/u', '', $text)]);
            }
        }
    }
};
