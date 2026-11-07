<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kuryer depodan malı götürəndə çəkdiyi şəkil (fayl adı; fayl — storage/app/private/pick-photos, PickPhotoStorage) */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_item_allocations', function (Blueprint $table) {
            $table->string('pick_photo', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_item_allocations', fn (Blueprint $table) => $table->dropColumn('pick_photo'));
    }
};
