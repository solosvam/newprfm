<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** CRM-də müştəri yaradılanda (şifrə SMS ilə) göndərilən mesaj — SMS şablonlarından redaktə olunur */
return new class extends Migration {
    public function up(): void
    {
        DB::table('sms_templates')->updateOrInsert(['code' => 'crm_customer_created'], [
            'name' => 'CRM — müştəri yaradıldı (şifrə)',
            'template' => 'Hormetli {fullname}, Parfumshop hesabiniz yaradildi. Sifreniz: {password}',
            'active' => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('code', 'crm_customer_created')->delete();
    }
};
