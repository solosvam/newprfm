<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ödəniş üsulunun adı əvvəlki kimi "Hissə-hissə ödəniş" — "(Bank kartı olmadan)" adın altında izah kimi yazılır
 * (checkout_payment_hint_installment). 2026_11_14_100000 / 110000-in dəyişdiyi ad geri qaytarılır.
 */
return new class extends Migration
{
    private const ORIGINAL = ['name_az' => 'Hissə-hissə ödəniş', 'name_ru' => 'Оплата в рассрочку', 'name_en' => 'Installment payment'];

    private const CHANGED = [
        'name_az' => ['Bank kartı olmadan hissə-hissə ödəniş', 'Hissə-hissə ödəniş (Bank kartı olmadan)'],
        'name_ru' => ['Рассрочка без банковской карты', 'Оплата в рассрочку (без банковской карты)'],
        'name_en' => ['Installments without a bank card', 'Installment payment (without a bank card)'],
    ];

    public function up(): void
    {
        foreach (self::ORIGINAL as $column => $value) {
            if (Schema::hasColumn('payment_methods', $column)) {
                DB::table('payment_methods')->where('code', 'installment')->whereIn($column, self::CHANGED[$column])->update([$column => $value]);
            }
        }
    }

    public function down(): void {}
};
