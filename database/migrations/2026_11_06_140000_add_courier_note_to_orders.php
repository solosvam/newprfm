<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Operatorun kuryer üçün qeydi (CRM-də sifariş yaradanda / kuryer təyin edəndə) — kuryerin səhifəsində rəngli göstərilir */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'courier_note')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('courier_note')->nullable()->after('customer_note');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('courier_note'));
    }
};
