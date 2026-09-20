<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_surname',
                'customer_mobile',
                'address_title',
                'city',
                'district',
                'address',
                'building',
                'entrance',
                'floor',
                'apartment',
                'address_note',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name', 60)->nullable();
            $table->string('customer_surname', 60)->nullable();
            $table->string('customer_mobile', 20)->nullable();
            $table->string('address_title', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('building', 50)->nullable();
            $table->string('entrance', 50)->nullable();
            $table->string('floor', 30)->nullable();
            $table->string('apartment', 30)->nullable();
            $table->text('address_note')->nullable();
        });
    }
};