<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** CRM-də müştəriyə yeni şifrə: mətn kodda idi (ə/ş hərfləri — 1 SMS 70 simvol), indi şablondur, latın hərfləri ilə */
return new class extends Migration {
    public function up(): void
    {
        DB::table('sms_templates')->updateOrInsert(['code' => 'crm_password_reset'], [
            'name' => 'CRM-də müştəriyə yeni şifrə',
            'template' => 'Hormetli {name}, Parfumshop hesabiniz ucun yeni sifreniz: {password}',
            'active' => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('code', 'crm_password_reset')->delete();
    }
};
