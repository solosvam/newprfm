<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Təminat hissəsinin axını: selected → notified → reserved → picked (+ problem, cancelled).
 * status sütunu artıq var; problemin növü ayrıca saxlanır (tarixçə allocation_status_logs-dadır).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_item_allocations', function (Blueprint $table) {
            $table->string('problem_type', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_allocations', function (Blueprint $table) {
            $table->dropColumn('problem_type');
        });
    }
};
