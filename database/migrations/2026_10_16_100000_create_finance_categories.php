<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maliyyə Clopos üslubunda: Mədaxil / Məxaric / Köçürmə + kateqoriyalar.
 *  - Mədaxil və məxaricin qarşı tərəfi "Kənar" sistem hesabıdır (köhnə "Xərclər" hesabı ona çevrilir).
 *  - "Xərc" əməliyyatları → Məxaric, kateqoriya "Digər xərclər" (xərcin adı qeyddə qalır).
 *  - Sahibkar artıq hesab deyil, kateqoriyadır: sahibkarın qoyduğu pul — Mədaxil, götürdüyü — Məxaric.
 *    Sahibkar hesabının başqa hərəkətləri də varsa (məs. anbara öz cibindən ödəyib), hesab "Sahibkar" şəxsi kimi qalır.
 *  - Hesabların açıqlaması; yeni "Kart" tipi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 10); // income | expense | both
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 40)->nullable()->unique(); // sistem kateqoriyaları
            $table->boolean('system')->default(false);         // dəyişdirilmir, silinmir
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('finance_categories')->restrictOnDelete();
        });

        Schema::table('money_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('kind');
            $table->foreign('category_id')->references('id')->on('finance_categories')->restrictOnDelete();
            $table->index('category_id');
        });

        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        $now = now();
        $categories = [];
        foreach ([
            ['sale', 'Satış', 'income', true],
            ['customer_refund', 'Müştəriyə qaytarma', 'expense', true],
            ['warehouse', 'Anbara ödəniş', 'expense', true],
            ['warehouse_refund', 'Anbar pulu qaytardı', 'income', true],
            ['transfer', 'Köçürmə', 'both', true],
            ['adjustment', 'Düzəliş', 'both', true],
            ['other_expense', 'Digər xərclər', 'expense', true],
            ['owner', 'Sahibkar', 'both', false],
        ] as [$code, $name, $type, $system]) {
            $categories[$code] = DB::table('finance_categories')->insertGetId([
                'name' => $name, 'type' => $type, 'code' => $code, 'system' => $system, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // "Xərclər" → "Kənar" (mədaxil və məxaricin qarşı tərəfi)
        $external = DB::table('finance_accounts')->where('code', 'expense')->value('id');
        if ($external) {
            DB::table('finance_accounts')->where('id', $external)->update(['type' => 'external', 'code' => 'external', 'name' => 'Kənar', 'updated_at' => $now]);
        } else {
            $external = DB::table('finance_accounts')->insertGetId(['type' => 'external', 'code' => 'external', 'name' => 'Kənar', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('money_movements')->where('kind', 'expense')->update(['kind' => 'outgo', 'category_id' => $categories['other_expense']]);

        // Sahibkar hesabı (köhnə "owner" və ya 15 oktyabr migrasiyasından sonrakı "Sahibkar" şəxsi)
        $owners = DB::table('finance_accounts')->where('type', 'owner')
            ->orWhere(fn ($q) => $q->where('type', 'person')->where('name', 'Sahibkar'))->pluck('id');
        foreach ($owners as $owner) {
            DB::table('money_movements')->where('from_account_id', $owner)->whereIn('kind', ['owner_contribution', 'income'])
                ->update(['from_account_id' => $external, 'kind' => 'income', 'category_id' => $categories['owner']]);
            DB::table('money_movements')->where('to_account_id', $owner)->whereIn('kind', ['owner_repayment', 'outgo'])
                ->update(['to_account_id' => $external, 'kind' => 'outgo', 'category_id' => $categories['owner']]);
            // Sahibkar öz cibindən xərc ödəyib: Məxaric sahibkar şəxsindən
            DB::table('money_movements')->where('from_account_id', $owner)->where('kind', 'outgo')->whereNull('category_id')
                ->update(['category_id' => $categories['other_expense']]);
            $used = DB::table('money_movements')->where('from_account_id', $owner)->orWhere('to_account_id', $owner)->exists();
            $used
                ? DB::table('finance_accounts')->where('id', $owner)->update(['type' => 'person', 'code' => null, 'name' => 'Sahibkar', 'updated_at' => $now])
                : DB::table('finance_accounts')->where('id', $owner)->delete();
        }

        // Avtomatik əməliyyatların kateqoriyası növündən
        foreach ([
            'customer_payment' => 'sale', 'customer_refund' => 'customer_refund',
            'warehouse_payment' => 'warehouse', 'warehouse_refund' => 'warehouse_refund',
            'transfer' => 'transfer', 'courier_advance' => 'transfer', 'courier_handover' => 'transfer',
        ] as $kind => $code) {
            DB::table('money_movements')->where('kind', $kind)->whereNull('category_id')->update(['category_id' => $categories[$code]]);
        }
        // Əks əməliyyat — əslinin kateqoriyası
        DB::table('money_movements')->where('kind', 'reversal')->whereNotNull('reversal_of_id')->orderBy('id')->get(['id', 'reversal_of_id'])
            ->each(fn ($r) => DB::table('money_movements')->where('id', $r->id)
                ->update(['category_id' => DB::table('money_movements')->where('id', $r->reversal_of_id)->value('category_id')]));
    }

    public function down(): void
    {
        Schema::table('money_movements', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });
        Schema::table('finance_accounts', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::dropIfExists('finance_categories');
    }
};
