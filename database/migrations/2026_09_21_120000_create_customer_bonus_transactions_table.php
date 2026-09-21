<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('customers',fn(Blueprint $t)=>$t->decimal('bonus_balance',12,2)->default(0));
  Schema::table('orders',function(Blueprint $t){$t->decimal('bonus_earned',12,2)->default(0);$t->decimal('bonus_used',12,2)->default(0);});
  Schema::create('customer_bonus_transactions',function(Blueprint $t){$t->id();$t->integer('customer_id');$t->unsignedBigInteger('order_id')->nullable();$t->enum('type',['earn','spend','register','adjustment']);$t->decimal('amount',12,2);$t->string('note')->nullable();$t->unsignedBigInteger('created_by')->nullable();$t->timestamps();$t->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();$t->foreign('order_id')->references('id')->on('orders')->nullOnDelete();});
 }
 public function down(): void {Schema::dropIfExists('customer_bonus_transactions');Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn(['bonus_earned','bonus_used']));Schema::table('customers',fn(Blueprint $t)=>$t->dropColumn('bonus_balance'));}
};
