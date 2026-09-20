<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('order_statuses',function(Blueprint $table){
   $table->id();
   $table->string('code',50)->unique();
   $table->string('name',100);
   $table->boolean('active')->default(true);
   $table->unsignedInteger('sort_order')->default(0);
   $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('order_statuses'); }
};