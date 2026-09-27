<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')->updateOrInsert(
            ['code' => 'bonus_balance'],
            [
                'name' => 'Bonus balansı ilə ödəniş',
                'name_az' => 'Bonus balansı ilə ödəniş',
                'name_en' => 'Pay with bonus balance',
                'name_ru' => 'Оплата бонусным балансом',
                // Enable after checkout validates and deducts the bonus balance.
                'active' => false,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('payment_methods')
            ->where('code', 'bonus_balance')
            ->delete();
    }
};
