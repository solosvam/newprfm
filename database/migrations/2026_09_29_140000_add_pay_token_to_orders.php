<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SMS ödəniş linki: parfumshop.az/p/{pay_token}
 * Yalnız onlayn ödənişli (card_online, birbank_installment) sifarişlərdə yaranır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pay_token', 16)->nullable()->unique()->after('order_no');
        });

        // Latın hərfləri: ə/ş/ç olsa SMS limiti 160-dan 70-ə düşür
        DB::table('sms_templates')->updateOrInsert(
            ['code' => 'order_payment_link'],
            [
                'name' => 'Sifariş — ödəniş linki',
                'template' => 'Parfumshop: {order_no} sifarisiniz ucun odenis linki: {link}',
                'active' => 1,
            ]
        );
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('code', 'order_payment_link')->delete();
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['pay_token']);
            $table->dropColumn('pay_token');
        });
    }
};
