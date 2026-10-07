<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Bonus şərtlərində çaşdıran "1 bonus = 1 ₼" ifadəsi çıxarılır (bonus balansı onsuz da manatla göstərilir) */
return new class extends Migration
{
    private const REPLACE = [
        'bonus_terms_az' => ['müştərilərə verilən hədiyyə balansıdır. 1 bonus = 1 ₼.', 'müştərilərə verilən hədiyyə balansıdır və manatla hesablanır.'],
        'bonus_terms_en' => ['Bonus is a reward balance for customers shopping at Parfumshop.az. 1 bonus = 1 ₼.', 'Bonus is a reward balance in manats for customers shopping at Parfumshop.az.'],
        'bonus_terms_ru' => ['Бонус — это подарочный баланс для покупателей Parfumshop.az. 1 бонус = 1 ₼.', 'Бонус — это подарочный баланс в манатах для покупателей Parfumshop.az.'],
    ];

    public function up(): void
    {
        foreach (self::REPLACE as $key => [$old, $new]) {
            $text = DB::table('settings')->where('key', $key)->value('value');
            if ($text !== null && str_contains($text, $old)) {
                DB::table('settings')->where('key', $key)->update(['value' => str_replace($old, $new, $text), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
    }
};
