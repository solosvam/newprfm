<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stoklu mal alışı (ilk növbədə hədiyyə kartları — HK 100 / 200 / 500 / 1000).
 * Hər kartın öz barkodu var (13 rəqəm: 1 + nominal 6 rəqəm + sıra 6 rəqəm, məs. HK 100 → 1000100000001).
 * Alış çap xərci ilə yazılır (kartın nominalı bizim mal deyil). Kartın hər hərəkəti — gift_card_events.
 * Satışda qalıq / fərqin ödənməsi / müddət qərarları hələ verilməyib — burada yalnız stok və status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchased_at');
            $table->string('supplier', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_purchase_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);                  // gift_card (sonra: öz brendimiz və s.)
            $table->decimal('denomination', 10, 2)->nullable(); // hədiyyə kartının nominalı
            $table->string('barcode_from', 20)->nullable();
            $table->string('barcode_to', 20)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 20)->unique();
            $table->decimal('denomination', 10, 2);
            $table->string('status', 15)->default('in_stock'); // in_stock | sold
            $table->foreignId('stock_purchase_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->unsignedBigInteger('sold_order_id')->nullable();
            $table->timestamps();
            $table->index(['denomination', 'status']);
        });

        Schema::create('gift_card_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);                      // received | sold | returned
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at');
        });

        DB::table('permissions')->updateOrInsert(
            ['name' => 'stock', 'guard_name' => 'admin'],
            ['description' => 'Stok: alışlar və hədiyyə kartları', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'stock')->where('guard_name', 'admin')->delete();
        Schema::dropIfExists('gift_card_events');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('stock_purchase_items');
        Schema::dropIfExists('stock_purchases');
    }
};
