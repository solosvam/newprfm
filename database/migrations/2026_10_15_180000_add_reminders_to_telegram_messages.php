<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cavabsız Telegram sorğusuna xatırlatma (telegram:remind): neçə dəfə və sonuncu nə vaxt */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->unsignedTinyInteger('reminders')->default(0)->after('has_photo');
            $table->timestamp('reminded_at')->nullable()->after('reminders');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->dropColumn(['reminders', 'reminded_at']);
        });
    }
};
