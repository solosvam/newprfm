<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Müştərinin haradan yarandığı (Customer::SOURCES): website, crm, assistant, easy_order, legacy.
 * Əvvəlki qeydlər: köhnə sistemdən gələnlər — legacy, qalanları məlum deyil (NULL).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('old_customer_id');
            $table->index(['source', 'created_at']);
        });
        DB::table('customers')->whereNotNull('old_customer_id')->update(['source' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['source', 'created_at']);
            $table->dropColumn('source');
        });
    }
};
