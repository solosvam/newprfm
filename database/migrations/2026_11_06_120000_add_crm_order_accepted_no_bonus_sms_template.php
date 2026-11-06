<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** CRM-dən operator sifariş yaradanda — bonus yazılmayacaqsa (məs. bonusla ödəniş) ayrıca mətn. Aktivliyi əsas şablonla eyni başlayır. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sms_templates')->where('code', 'crm_order_accepted_no_bonus')->exists()) {
            return;
        }
        DB::table('sms_templates')->insert([
            'code' => 'crm_order_accepted_no_bonus',
            'name' => 'CRM-dən operator sifariş qəbul etdikdə (bonus olmayanda)',
            'template' => 'Hormetli {name}, sizin sifarishiniz qebul olundu. Bizi secdiyiniz ucun teshekkur edirik!',
            'active' => (int) (DB::table('sms_templates')->where('code', 'crm_order_accepted')->value('active') ?? 0),
        ]);
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('code', 'crm_order_accepted_no_bonus')->delete();
    }
};
