<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sifariş statuslarının axını (OrderStatusService) və sifarişə kuryer.
 * Müştəri daxili mərhələləri (sorğu, anbar, kuryer təyini) "Hazırlanır" kimi görür — OrderStatus::CUSTOMER_MAP.
 * Status kodu artıq varsa toxunulmur (yalnız sıra); eyni adla başqa kodla yaradılıbsa kodu düzəldilir.
 */
return new class extends Migration {
    private const FLOW = [
        // code, az, en, ru
        ['new', 'Sifariş verildi', 'Order placed', 'Заказ оформлен'],
        ['preparing', 'Hazırlanır', 'Preparing', 'Готовится'],
        ['warehouse_requested', 'Anbarlara sorğu göndərildi', 'Warehouses requested', 'Запрос на склады'],
        ['warehouses_assigned', 'Anbarlar təyin olundu', 'Warehouses assigned', 'Склады назначены'],
        ['courier_assigned', 'Kuryer təyin olundu', 'Courier assigned', 'Курьер назначен'],
        ['sent', 'Yola çıxdı', 'On the way', 'В пути'],
        ['at_address', 'Kuryer ünvandadır', 'Courier at address', 'Курьер на месте'],
        ['delivered', 'Təhvil verildi', 'Delivered', 'Доставлен'],
        ['cancelled', 'Ləğv edildi', 'Cancelled', 'Отменён'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::FLOW as $i => [$code, $az, $en, $ru]) {
            $row = DB::table('order_statuses')->where('code', $code)->first()
                ?? DB::table('order_statuses')->where('name_az', $az)->first();
            $values = ['code' => $code, 'active' => 1, 'sort_order' => $i + 1, 'updated_at' => $now];
            if ($row) {
                DB::table('order_statuses')->where('id', $row->id)->update($values);
            } else {
                DB::table('order_statuses')->insert($values + ['name_az' => $az, 'name_en' => $en, 'name_ru' => $ru, 'created_at' => $now]);
            }
        }
        // Axında olmayanlar gizlədilir (köhnə qeydlər üçün saxlanılır)
        DB::table('order_statuses')->whereNotIn('code', array_column(self::FLOW, 0))->update(['active' => 0, 'sort_order' => 99]);

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('courier_id')->nullable()->after('created_by'); // users (Kuryer rolu)
            $table->index('courier_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['courier_id']);
            $table->dropColumn('courier_id');
        });
    }
};
