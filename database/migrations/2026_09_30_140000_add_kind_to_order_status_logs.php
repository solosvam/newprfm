<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status tarixçəsində kuryer/operator bildirişlərini ayırmaq üçün növ:
 *   delivery_problem — kuryer "Problem" bildirdi; door_refusal — qapıda imtina;
 *   warehouse_return — məhsul anbara qaytarıldı; courier_change — kuryer sifarişi ötürdü.
 * Adi status keçidlərində null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->string('kind', 30)->nullable()->after('note');
            $table->index(['order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
