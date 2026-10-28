<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** CRM: məhsul axtarışında son alış qiyməti, təchizatçı (anbar) və tarix — yalnız bu icazə ilə */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'crm.purchase_info', 'guard_name' => 'admin'],
            ['description' => 'CRM: son alış qiyməti, təchizatçı və tarixi görmək', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'crm.purchase_info')->where('guard_name', 'admin')->delete();
    }
};
