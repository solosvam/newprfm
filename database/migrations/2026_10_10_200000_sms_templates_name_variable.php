<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Müştəri özü saytdan sifariş verdikdə" şablonu silinir (heç vaxt göndərilməyib).
 * Şablonlarda müraciət {fullname} (ad və soyad) əvəzinə {name} (ad) ilə — kod hər ikisini doldurur.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('sms_templates')->where('code', 'website_order_accepted')->delete();

        foreach (DB::table('sms_templates')->where('template', 'like', '%{fullname}%')->get() as $template) {
            DB::table('sms_templates')->where('id', $template->id)->update(['template' => str_replace('{fullname}', '{name}', $template->template)]);
        }
    }

    public function down(): void
    {
        // Geri qaytarılmır: şablon mətnləri admin paneldən dəyişdirilir
    }
};
