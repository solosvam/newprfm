<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sifariş bonusu artıq yalnız təhvildə yazılır (BonusService::earnOnDelivery): bonus şərtlərindəki
 * "ləğvdə bonus çıxılır" bəndi yeni qayda ilə əvəz olunur — admin-də saxlanmış mətndə (dəyişdirilməyibsə).
 */
return new class extends Migration {
    private const TERMS = [
        'bonus_terms_az' => ['• Sifariş ləğv edildikdə və ya qaytarıldıqda həmin sifarişdən qazanılan bonus balansdan çıxılır.', '• Sifariş bonusu sifariş təhvil verildikdən sonra hesabınıza yazılır. Bonus balansı və ya hissə-hissə ödənişlə alınan sifarişlərə bonus verilmir.'],
        'bonus_terms_en' => ['• If an order is cancelled or returned, the bonus earned from it is deducted from your balance.', '• The order bonus is credited to your account after the order is delivered. Orders paid with bonus balance or in installments do not earn bonus.'],
        'bonus_terms_ru' => ['• При отмене или возврате заказа начисленный за него бонус списывается с баланса.', '• Бонус за заказ начисляется после его доставки. За заказы, оплаченные бонусным балансом или в рассрочку, бонус не начисляется.'],
    ];

    public function up(): void
    {
        $this->swap(0, 1);
    }

    public function down(): void
    {
        $this->swap(1, 0);
    }

    private function swap(int $from, int $to): void
    {
        foreach (self::TERMS as $key => $texts) {
            $value = DB::table('settings')->where('key', $key)->value('value');
            if (is_string($value) && str_contains($value, $texts[$from])) {
                DB::table('settings')->where('key', $key)->update(['value' => str_replace($texts[$from], $texts[$to], $value), 'updated_at' => now()]);
            }
        }
    }
};
