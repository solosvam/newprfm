<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anbarlara Telegram bildirişləri: göndərilən mesajlar jurnala (sms_logs, channel = telegram) yazılır;
 * düyməli mesajların vəziyyəti (cavab gözlənilir, say / qiymət soruşulur) — telegram_messages.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('channel', 10)->default('sms')->after('context');
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 32);
            $table->unsignedBigInteger('message_id');
            $table->string('kind', 20);                      // request_item | allocation | prompt
            $table->unsignedBigInteger('subject_id')->nullable(); // warehouse_request_items.id / order_item_allocations.id
            $table->unsignedBigInteger('parent_id')->nullable();  // prompt → əsas mesaj
            $table->string('state', 20)->nullable();         // await_quantity | await_price
            $table->unsignedInteger('quantity')->nullable(); // anbarın verdiyi say (qiymət soruşularkən)
            $table->text('text');                            // əsas mesajın mətni (HTML) — redaktədə yenidən qurulur
            $table->boolean('has_photo')->default(false);
            $table->timestamps();
            $table->index(['chat_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
