<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('status_id')->nullable()->after('order_id');
            $table->foreign('status_id')->references('id')->on('order_statuses');
        });

        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 30)->nullable()->after('source');
        });

        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->string('status', 30)->nullable()->after('order_id');
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};