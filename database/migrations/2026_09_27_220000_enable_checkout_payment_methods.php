<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_methods')->where('code', 'm10')->update(['active' => false]);
        DB::table('payment_methods')->whereIn('code', ['cash', 'card_online', 'installment', 'bonus_balance'])->update(['active' => true]);
    }

    public function down(): void
    {
        DB::table('payment_methods')->whereIn('code', ['installment', 'bonus_balance'])->update(['active' => false]);
    }
};
