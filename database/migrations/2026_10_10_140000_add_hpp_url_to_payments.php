<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankın ödəniş səhifəsinin ünvanı: müştəri səhifəni bağlayıb qayıdanda yarımçıq ödənişə
 * eyni bank sifarişi ilə davam edir (yeni ödəniş yaranmır) — BirbankPaymentSync::resumePending.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('hpp_url', 500)->nullable()->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('hpp_url');
        });
    }
};
