<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'product.search', 'guard_name' => 'admin'],
            [
                'description' => 'Məhsul axtarışının idarə olunması və analitikası',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'product.search')
            ->where('guard_name', 'admin')
            ->delete();
    }
};
