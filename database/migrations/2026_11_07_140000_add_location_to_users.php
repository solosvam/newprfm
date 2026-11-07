<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kuryerin son yeri: kuryer tətbiqi hər açılanda göndərir (CourierAppController::location), admin Kuryerlər səhifəsində görür */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy')->nullable(); // metr
            $table->timestamp('location_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['location_lat', 'location_lng', 'location_accuracy', 'location_at']));
    }
};
