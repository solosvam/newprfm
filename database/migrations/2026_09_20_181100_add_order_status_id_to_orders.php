<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('orders',function(Blueprint $table){
   $table->unsignedBigInteger('order_status_id')->nullable()->after('source');
   $table->foreign('order_status_id')->references('id')->on('order_statuses');
  });
 }
 public function down(): void {
  Schema::table('orders',function(Blueprint $table){$table->dropForeign(['order_status_id']);$table->dropColumn('order_status_id');});
 }
};