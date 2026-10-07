<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Əlaqə səhifəsində telefon/e-poçt artıq ayarlardan kart kimi göstərilir (Ayarlar → Əlaqə məlumatları).
 * İlkin mətndə sabit yazılmış nömrələr təkrarlanmasın — mətn qısa girişlə əvəz olunur (yalnız dəyişdirilməyibsə).
 */
return new class extends Migration
{
    private const BODIES = [
        'az' => '<p>Sualınız, təklifiniz və ya sifarişlə bağlı müraciətiniz varsa, bizimlə istənilən rahat yolla əlaqə saxlayın — operatorlarımız iş saatlarında cavab verir.</p>',
        'en' => '<p>If you have a question, suggestion or an enquiry about an order, contact us in any convenient way — our operators reply during working hours.</p>',
        'ru' => '<p>Если у вас есть вопрос, предложение или обращение по заказу, свяжитесь с нами любым удобным способом — операторы отвечают в рабочее время.</p>',
    ];

    public function up(): void
    {
        $page = DB::table('pages')->where('key', 'contact')->first();
        if (!$page) {
            return;
        }
        $update = [];
        foreach (self::BODIES as $locale => $body) {
            if (str_contains((string) $page->{'body_'.$locale}, 'tel:+994123102255')) {
                $update['body_'.$locale] = $body;
            }
        }
        if ($update) {
            DB::table('pages')->where('id', $page->id)->update($update + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
