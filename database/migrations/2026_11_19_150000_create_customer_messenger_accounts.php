<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müştərinin messencer hesabları (müştəri botu): indi Telegram (@parfumshopai_bot), sonra WhatsApp Business.
 * external_id — kanalın müştəri ID-si (Telegram user id; WhatsApp-da nömrə). Bir müştərinin hər kanalda bir hesabı olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_messenger_accounts')) {
            return;
        }
        Schema::create('customer_messenger_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id');
            $table->string('channel', 20);
            $table->string('external_id', 64);
            $table->string('phone', 15)->nullable();      // kanalın təsdiqlədiyi nömrə (Telegram kontaktı) və ya SMS ilə təsdiqlənən
            $table->string('display_name', 100)->nullable();
            $table->string('linked_via', 20);             // contact / sms / site / register
            $table->timestamps();

            $table->unique(['channel', 'external_id']);
            $table->unique(['customer_id', 'channel']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_messenger_accounts');
    }
};
