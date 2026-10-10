<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müştəri sifarişdən saytdan özü imtina edəndə ləğvi yaradan əməkdaş yoxdur (created_by = NULL).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_item_cancellations', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Geri qaytarılmır: müştərinin öz ləğvləri (NULL) NOT NULL sütuna sığmaz
    }
};
