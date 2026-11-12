<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** CRM-dən operator hissə-hissə ödənişli sifariş yaradanda ayrıca mətn ({months}, {monthly}, {total}). Aktivliyi əsas şablonla eyni başlayır. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('sms_templates')->where('code', 'crm_order_accepted_installment')->exists()) {
            return;
        }
        DB::table('sms_templates')->insert([
            'code' => 'crm_order_accepted_installment',
            'name' => 'CRM-dən operator hissə-hissə ödənişli sifariş qəbul etdikdə',
            'template' => 'Hormetli {name}, {order_no} nomreli sifarishiniz hisse-hisse odenishle qebul olundu: {months} ay, ayliq {monthly} AZN, cemi {total} AZN. Bizi secdiyiniz ucun teshekkur edirik!',
            'active' => (int) (DB::table('sms_templates')->where('code', 'crm_order_accepted')->value('active') ?? 0),
        ]);
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('code', 'crm_order_accepted_installment')->delete();
    }
};
