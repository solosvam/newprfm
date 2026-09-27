<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('name_az', 100)->nullable()->after('name');
            $table->string('name_en', 100)->nullable()->after('name_az');
            $table->string('name_ru', 100)->nullable()->after('name_en');
        });

        $names = [
            'cash' => ['Qapıda nağd', 'Cash on delivery', 'Наличными при доставке'],
            'm10' => ['M10 ilə ödəniş', 'Pay with M10', 'Оплата через M10'],
            'online_card' => ['Onlayn kartla ödəniş', 'Online card payment', 'Онлайн-оплата картой'],
        ];

        foreach ($names as $code => [$az, $en, $ru]) {
            DB::table('payment_methods')->where('code', $code)->update([
                'name' => $az,
                'name_az' => $az,
                'name_en' => $en,
                'name_ru' => $ru,
                'updated_at' => now(),
            ]);
        }

        // Rename only the code, not the ID: existing orders retain their payment method.
        DB::table('payment_methods')->where('code', 'online_card')->update([
            'code' => 'card_online',
            'updated_at' => now(),
        ]);

        // Keep inactive until checkout is connected to the credit application flow.
        DB::table('payment_methods')->updateOrInsert(
            ['code' => 'installment'],
            [
                'name' => 'Hissə-hissə ödəniş',
                'name_az' => 'Hissə-hissə ödəniş',
                'name_en' => 'Installment payment',
                'name_ru' => 'Оплата в рассрочку',
                'active' => false,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('payment_methods')->where('code', 'installment')->delete();
        DB::table('payment_methods')->where('code', 'card_online')->update(['code' => 'online_card']);

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['name_az', 'name_en', 'name_ru']);
        });
    }
};
