<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'crm', 'guard_name' => 'admin'],
            [
                'description' => 'Müştəri əlaqələrinin idarə olunması',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'crm')
            ->where('guard_name', 'admin')
            ->delete();
    }
};
