<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ödəniş üsulunun adı: "Hissə-hissə ödəniş (Bank kartı olmadan)" — əvvəlki standart addan və ya 2026_11_14_100000-in
 * yazdığı addan. Əl ilə başqa ad yazılıbsa toxunulmur.
 */
return new class extends Migration
{
    private const FINAL = ['name_az' => 'Hissə-hissə ödəniş (Bank kartı olmadan)', 'name_ru' => 'Оплата в рассрочку (без банковской карты)',
        'name_en' => 'Installment payment (without a bank card)'];

    private const PREVIOUS = [
        'name_az' => ['Hissə-hissə ödəniş', 'Bank kartı olmadan hissə-hissə ödəniş'],
        'name_ru' => ['Оплата в рассрочку', 'Рассрочка без банковской карты'],
        'name_en' => ['Installment payment', 'Installments without a bank card'],
    ];

    public function up(): void
    {
        foreach (self::FINAL as $column => $value) {
            if (Schema::hasColumn('payment_methods', $column)) {
                DB::table('payment_methods')->where('code', 'installment')->whereIn($column, self::PREVIOUS[$column])->update([$column => $value]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::FINAL as $column => $value) {
            if (Schema::hasColumn('payment_methods', $column)) {
                DB::table('payment_methods')->where('code', 'installment')->where($column, $value)->update([$column => self::PREVIOUS[$column][0]]);
            }
        }
    }
};
