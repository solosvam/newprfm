<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('promo_codes',function(Blueprint $t){
   $t->id();$t->string('code',50)->unique();$t->enum('type',['percent','fixed']);$t->decimal('value',12,2);
   $t->decimal('min_amount',12,2)->nullable();$t->decimal('max_discount',12,2)->nullable();
   $t->boolean('is_active')->default(true);$t->timestamp('starts_at')->nullable();$t->timestamp('expires_at')->nullable();
   $t->unsignedInteger('usage_limit')->nullable();$t->unsignedInteger('used_count')->default(0);$t->timestamps();
  });
  Schema::table('orders',function(Blueprint $t){$t->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();$t->decimal('delivery_fee',12,2)->default(0);});
 }
 public function down():void {Schema::table('orders',function(Blueprint $t){$t->dropConstrainedForeignId('promo_code_id');$t->dropColumn('delivery_fee');});Schema::dropIfExists('promo_codes');}
};
