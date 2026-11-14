<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Hissə-hissə ödəniş" (Ferrum) Birbank taksit ilə qarışmasın: ödəniş üsulunun adı — "Bank kartı olmadan hissə-hissə ödəniş".
 * Yalnız köhnə standart ad dəyişdirilir (əl ilə başqa ad yazılıbsa toxunulmur).
 */
return new class extends Migration
{
    private const OLD = ['name' => 'Hissə-hissə ödəniş', 'name_az' => 'Hissə-hissə ödəniş', 'name_ru' => 'Оплата в рассрочку', 'name_en' => 'Installment payment'];

    private const NEW = ['name' => 'Bank kartı olmadan hissə-hissə ödəniş', 'name_az' => 'Bank kartı olmadan hissə-hissə ödəniş',
        'name_ru' => 'Рассрочка без банковской карты', 'name_en' => 'Installments without a bank card'];

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(array $from, array $to): void
    {
        foreach ($to as $column => $value) {
            if (!Schema::hasColumn('payment_methods', $column)) {
                continue;
            }
            DB::table('payment_methods')->where('code', 'installment')->where($column, $from[$column])->update([$column => $value]);
        }
    }
};
