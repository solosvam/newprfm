<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dəvət olunanın ilk sifariş endirimi (referal, "İlk sifarişdə endirim" rejimi).
 * orders.discount-a DAXİLDİR (promo + referal) — ödəniş sətirləri, ləğv və bonus hesabı eyni qalır;
 * bu sütun yalnız endirimin hansı hissəsinin referaldan gəldiyini göstərir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('referral_discount', 10, 2)->default(0)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('referral_discount');
        });
    }
};
