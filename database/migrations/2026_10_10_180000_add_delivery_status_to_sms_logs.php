<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS jurnalı bütün göndərişləri əhatə edir (əvvəl yalnız anbar bildirişləri yazılırdı);
 * çatdırılma statusu lsim hesabatından (sms:check-delivery) yazılır.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('delivery_status')->nullable()->after('provider_id'); // lsim: 100–109
            $table->timestamp('delivery_checked_at')->nullable()->after('delivery_status');
            $table->index(['status', 'delivery_status']);
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['status', 'delivery_status']);
            $table->dropColumn(['delivery_status', 'delivery_checked_at']);
        });
    }
};
