<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sifariş statusları və tarixçə cədvəli (yaddaşdakı sqlite üçün).
 * id 1 = "preparing" (icraya götürülüb) — testlərdəki sifarişlər anbar əməliyyatına hazırdır.
 */
trait OrderStatusFixtures
{
    protected function orderStatusFixtures(): void
    {
        if (!Schema::hasTable('order_status_logs')) {
            Schema::create('order_status_logs', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('status_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable(); $t->text('note')->nullable(); $t->string('kind', 30)->nullable(); $t->timestamps();
            });
        }
        $codes = ['preparing' => 1, 'new' => 11, 'warehouse_requested' => 12, 'warehouses_assigned' => 13,
            'courier_assigned' => 14, 'sent' => 15, 'at_address' => 16, 'delivered' => 17, 'cancelled' => 18];
        foreach ($codes as $code => $id) {
            if (!DB::table('order_statuses')->where('code', $code)->exists() && !DB::table('order_statuses')->where('id', $id)->exists()) {
                DB::table('order_statuses')->insert(['id' => $id, 'code' => $code]);
            }
        }
    }

    protected function statusCode(\App\Models\Order\Order $order): ?string
    {
        return DB::table('order_statuses')->where('id', $order->fresh()->order_status_id)->value('code');
    }
}
