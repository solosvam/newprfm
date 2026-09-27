<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_methods')->where('code', 'cash')->update([
            'name_az' => 'Qapıda ödəniş', 'name_en' => 'Pay on delivery',
            'name_ru' => 'Оплата при доставке',
        ]);
        DB::table('payment_methods')->where('code', 'card_online')->update([
            'name_az' => 'Onlayn ödəniş', 'name_en' => 'Online payment',
            'name_ru' => 'Онлайн-оплата',
        ]);
        // Keep disabled until the merchant-specific Birbank installment API
        // contract and callback verification have been implemented.
        DB::table('payment_methods')->where('code', 'm10')->update([
            'code' => 'birbank_installment',
            'name_az' => 'Birbank taksit kartı',
            'name_en' => 'Birbank installment card',
            'name_ru' => 'Рассрочка Birbank',
            'active' => 0,
        ]);
        if (Schema::hasColumn('payment_methods', 'name')) {
            Schema::table('payment_methods', fn (Blueprint $table) => $table->dropColumn('name'));
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('payment_methods', 'name')) {
            Schema::table('payment_methods', fn (Blueprint $table) => $table->string('name')->nullable());
        }
        DB::table('payment_methods')->where('code', 'birbank_installment')->update([
            'code' => 'm10', 'name' => 'M10 ilə ödəniş', 'name_az' => 'M10 ilə ödəniş',
            'name_en' => 'Pay with M10', 'name_ru' => 'Оплата через M10', 'active' => 0,
        ]);
        DB::table('payment_methods')->where('code', 'cash')->update([
            'name' => 'Qapıda nağd', 'name_az' => 'Qapıda nağd',
            'name_en' => 'Cash on delivery', 'name_ru' => 'Наличными при доставке',
        ]);
        DB::table('payment_methods')->where('code', 'card_online')->update([
            'name' => 'Onlayn kartla ödəniş', 'name_az' => 'Onlayn kartla ödəniş',
            'name_en' => 'Online card payment', 'name_ru' => 'Онлайн-оплата картой',
        ]);
    }
};
