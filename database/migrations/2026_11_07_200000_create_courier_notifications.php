<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuryerin bildirişləri (kuryer tətbiqi → Bildirişlər): push ilə eyni anda yazılır (CourierNotifier).
 * Oxunmamışların sayı — tətbiqin ikonunda və zəngin üstündə; 30 gündən köhnələr silinir (routes/console.php).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('courier_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('type', 30);
            $table->string('title', 150);
            $table->string('body', 255)->nullable();
            $table->string('url', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_notifications');
    }
};
