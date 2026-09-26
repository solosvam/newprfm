<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['new', 'Sifariş verildi', 'Order placed', 'Заказ оформлен', 1],
            ['confirmed', 'Təsdiqləndi', 'Confirmed', 'Подтверждён', 2],
            ['preparing', 'Hazırlanır', 'Preparing', 'Готовится', 3],
            ['sent', 'Göndərildi', 'Shipped', 'Отправлен', 4],
            ['courier', 'Kuryerə verildi', 'Handed to courier', 'Передан курьеру', 5],
            ['delivered', 'Təhvil verildi', 'Delivered', 'Доставлен', 6],
            ['cancelled', 'Ləğv edildi', 'Cancelled', 'Отменён', 7],
        ];

        foreach ($statuses as [$code, $az, $en, $ru, $sort]) {
            DB::table('order_statuses')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $az,
                    'name_az' => $az,
                    'name_en' => $en,
                    'name_ru' => $ru,
                    'active' => 1,
                    'sort_order' => $sort,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
