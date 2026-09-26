<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->string('name_az')->nullable()->after('name');
            $table->string('name_en')->nullable()->after('name_az');
            $table->string('name_ru')->nullable()->after('name_en');
        });

        $translations = [
            'new' => ['Sifariş verildi', 'Order placed', 'Заказ оформлен'],
            'confirmed' => ['Təsdiqləndi', 'Confirmed', 'Подтверждён'],
            'preparing' => ['Hazırlanır', 'Preparing', 'Готовится'],
            'sent' => ['Göndərildi', 'Shipped', 'Отправлен'],
            'courier' => ['Kuryerə verildi', 'Handed to courier', 'Передан курьеру'],
            'delivered' => ['Təhvil verildi', 'Delivered', 'Доставлен'],
            'cancelled' => ['Ləğv edildi', 'Cancelled', 'Отменён'],
        ];

        foreach ($translations as $code => [$az, $en, $ru]) {
            DB::table('order_statuses')->where('code', $code)->update([
                'name_az' => $az,
                'name_en' => $en,
                'name_ru' => $ru,
            ]);
        }

        // Preserve custom statuses created by administrators.
        DB::table('order_statuses')->whereNull('name_az')->update(['name_az' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn(['name_az', 'name_en', 'name_ru']);
        });
    }
};
