<?php

use App\Models\Order\Order;
use App\Services\Payment\PaymentItemsBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ödənişə daxil olanlar: hansı məhsul, neçə ədəd, nə qədər (promo payı çıxılmış), çatdırılma, qablaşdırma.
 * Sonradan geri ödəniş konkret sətrə bağlanacaq.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('order_item_id')->nullable(); // çatdırılma/qablaşdırmada null
            $table->string('type', 20); // item | delivery | gift_wrap
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            $table->index(['payment_id', 'type']);
        });

        // Mövcud ödənişlər: sifarişin hazırkı tərkibindən doldurulur
        $builder = app(PaymentItemsBuilder::class);
        DB::table('payments')->orderBy('id')->chunkById(200, function ($payments) use ($builder) {
            $orders = Order::with('items')->whereIn('id', $payments->pluck('order_id'))->get()->keyBy('id');
            $now = now();
            foreach ($payments as $payment) {
                $order = $orders->get($payment->order_id);
                if (!$order) continue;
                $rows = array_map(fn ($line) => $line + ['payment_id' => $payment->id, 'created_at' => $now, 'updated_at' => $now],
                    $builder->lines($order, (float) $payment->amount));
                DB::table('payment_items')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_items');
    }
};
