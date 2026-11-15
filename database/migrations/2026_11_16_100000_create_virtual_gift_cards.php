<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Virtual hədiyyə kartı (köhnə fiziki "gift_cards" cədvəli ilə əlaqəsi yoxdur — o, 2026_11_15_120000-də ləğv olunub).
 *
 *  - virtual_gift_cards: müştəri onlayn alır (yalnız Birbank), kod ödənişdən sonra yaranır, 12 ay keçərlidir.
 *  - Kart səbətdə ödəniş kimi tətbiq olunur: orders.gift_card_amount — kartla ödənilən hissə,
 *    orders.total — qalan, seçilən üsulla ödənilən məbləğ (kart hamısını örtürsə 0, üsul "gift_card").
 *  - payments.order_id boş ola bilər: kartın öz alışı sifariş deyil (payments.virtual_gift_card_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hər addım yoxlanır: MySQL-də DDL tranzaksiyada deyil — yarımçıq qalsa təkrar işlədilə bilsin
        if (!Schema::hasTable('virtual_gift_cards')) Schema::create('virtual_gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();      // ödənişdən sonra; tire olmadan saxlanır
            $table->decimal('amount', 10, 2);
            $table->string('design', 20)->default('standard');
            $table->string('message', 150)->nullable();
            $table->string('sender_name', 60)->nullable();
            $table->integer('customer_id');                         // alan müştəri (customers.id INT-dir)
            $table->string('status', 15)->default('pending');       // pending | active | used | expired | refunded
            $table->string('share_token', 48)->unique();            // paylaşılan link
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('used_order_id')->nullable()->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index('customer_id');
        });

        if (!Schema::hasTable('virtual_gift_card_events')) Schema::create('virtual_gift_card_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_gift_card_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });
        if (!Schema::hasColumn('payments', 'virtual_gift_card_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('virtual_gift_card_id')->nullable()->after('order_id')->index();
            });
        }

        if (!Schema::hasColumn('orders', 'gift_card_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('gift_card_id')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('orders', 'gift_card_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('gift_card_amount', 10, 2)->default(0);
            });
        }

        // Kart sifarişin hamısını örtəndə — checkout-da seçilmir (active = false)
        DB::table('payment_methods')->updateOrInsert(['code' => 'gift_card'], [
            'name_az' => 'Hədiyyə kartı', 'name_en' => 'Gift card', 'name_ru' => 'Подарочная карта',
            'active' => false, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Super admin-ə verilir; digər rollara — Rollar səhifəsindən
        $role = Schema::hasTable('roles') ? DB::table('roles')->where('name', 'Super admin')->where('guard_name', 'admin')->value('id') : null;
        foreach (['giftcard.list' => 'Hədiyyə kartları: siyahı', 'giftcard.manage' => 'Hədiyyə kartları: müddətin uzadılması, pulun qaytarılması'] as $name => $description) {
            DB::table('permissions')->updateOrInsert(['name' => $name, 'guard_name' => 'admin'],
                ['description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            $permission = DB::table('permissions')->where('name', $name)->where('guard_name', 'admin')->value('id');
            if ($permission && $role && Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
            }
        }
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['giftcard.list', 'giftcard.manage'])->where('guard_name', 'admin')->delete();
        DB::table('payment_methods')->where('code', 'gift_card')->delete();
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['gift_card_id', 'gift_card_amount']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('virtual_gift_card_id');
        });
        Schema::dropIfExists('virtual_gift_card_events');
        Schema::dropIfExists('virtual_gift_cards');
    }
};
