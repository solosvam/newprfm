<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Bonusla ödəniş həddi" ayarı ləğv olunub: bonus balansı ayrıca ödəniş üsuludur və sifarişi tam ödəyir.
 * Admin-də saxlanmış bonus şərtlərindəki hədd bəndi də yeni mətnlə əvəz olunur (yalnız dəyişdirilməyibsə).
 */
return new class extends Migration {
    private const TERMS = [
        'bonus_terms_az' => ['• Bonusla sifarişin müəyyən hissəsini ödəmək mümkündür; həddi sifariş səhifəsində göstərilir.', '• Bonus balansı sifarişin yekun məbləğini tam ödəməlidir.'],
        'bonus_terms_en' => ['• Bonuses can cover a part of the order; the limit is shown on the order page.', '• Your bonus balance must cover the full order total.'],
        'bonus_terms_ru' => ['• Бонусами можно оплатить часть заказа; лимит указан на странице заказа.', '• Бонусного баланса должно хватать на всю сумму заказа.'],
    ];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', ['bonus_pay_limit_enabled', 'bonus_pay_percent'])->delete();

        foreach (self::TERMS as $key => [$old, $new]) {
            $value = DB::table('settings')->where('key', $key)->value('value');
            if (is_string($value) && str_contains($value, $old)) {
                DB::table('settings')->where('key', $key)->update(['value' => str_replace($old, $new, $value), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TERMS as $key => [$old, $new]) {
            $value = DB::table('settings')->where('key', $key)->value('value');
            if (is_string($value) && str_contains($value, $new)) {
                DB::table('settings')->where('key', $key)->update(['value' => str_replace($new, $old, $value), 'updated_at' => now()]);
            }
        }
    }
};
