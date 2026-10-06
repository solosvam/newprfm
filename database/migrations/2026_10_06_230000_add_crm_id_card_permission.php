<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Vəsiqə şəkillərinə baxış "crm"-dən ayrıca icazədir (admin.crm.id-card route-u, CRM və Assistant önizləmələri) */
return new class extends Migration {
    public function up(): void
    {
        if (DB::table('permissions')->where('name', 'crm.id_card')->where('guard_name', 'admin')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'crm.id_card', 'guard_name' => 'admin',
            'description' => 'Şəxsiyyət vəsiqəsinə baxma icazəsi',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'crm.id_card')->where('guard_name', 'admin')->delete();
    }
};
