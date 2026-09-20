<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('customer_addresses',function(Blueprint $table){$table->id();$table->unsignedInteger('customer_id');$table->string('title',50)->nullable();$table->string('city',100);$table->string('district',100)->nullable();$table->string('address',500);$table->string('building',50)->nullable();$table->string('entrance',50)->nullable();$table->string('floor',30)->nullable();$table->string('apartment',30)->nullable();$table->text('note')->nullable();$table->decimal('latitude',10,7)->nullable();$table->decimal('longitude',10,7)->nullable();$table->boolean('is_default')->default(false);$table->timestamps();$table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();}); }
 public function down(): void { Schema::dropIfExists('customer_addresses'); }
};