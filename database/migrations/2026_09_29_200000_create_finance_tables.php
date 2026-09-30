<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pul hərəkətləri: hər qeyd "haradan → haraya, nə qədər". Hesabın qalığı = daxil olan − çıxan.
 * Qeyd silinmir — səhv əks əməliyyatla (reversal_of_id) düzəldilir.
 * Öz hesablar arasında köçürmə xərc deyil; xərc yalnız "expense" hesablarına gedən puldur.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            // cash | bank | online | courier | owner | warehouse | expense | customer
            $table->string('type', 20);
            $table->string('code', 40)->nullable()->unique(); // sistem hesabları: cash, bank, online, owner, customer, expense
            $table->string('name', 150);
            $table->unsignedBigInteger('user_id')->nullable();      // kuryer
            $table->unsignedBigInteger('warehouse_id')->nullable(); // anbar
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'user_id']);
            $table->unique(['type', 'warehouse_id']);
        });

        Schema::create('money_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_account_id');
            $table->unsignedBigInteger('to_account_id');
            $table->decimal('amount', 12, 2);
            $table->string('kind', 30);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_allocation_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('payment_operation_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable()->unique(); // bir qeyd yalnız bir dəfə əks olunur
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // null — sistem (avtomatik)
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('from_account_id')->references('id')->on('finance_accounts')->restrictOnDelete();
            $table->foreign('to_account_id')->references('id')->on('finance_accounts')->restrictOnDelete();
            $table->foreign('reversal_of_id')->references('id')->on('money_movements')->restrictOnDelete();
            $table->index('order_id');
            $table->index('order_item_allocation_id');
            $table->index(['kind', 'payment_id']);
            $table->index('payment_operation_id');
            $table->index('occurred_at');
        });

        $now = now();
        $system = [
            ['cash', 'cash', 'Mərkəzi nağd kassa'],
            ['bank', 'bank', 'Şirkət bank hesabı'],
            ['online', 'online', 'Onlayn ödənişlər (Birbank)'],
            ['owner', 'owner', 'Sahibkar'],
            ['customer', 'customer', 'Müştərilər'],
            ['expense', 'expense', 'Xərclər'], // xərcin adı əməliyyatın qeydində yazılır
        ];
        foreach ($system as [$type, $code, $name]) {
            DB::table('finance_accounts')->insert(['type' => $type, 'code' => $code, 'name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['name' => 'finance', 'guard_name' => 'admin'],
                ['description' => 'Kassa, pul hərəkətləri və hesablaşmalar', 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('money_movements');
        Schema::dropIfExists('finance_accounts');
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('name', 'finance')->where('guard_name', 'admin')->delete();
        }
    }
};
