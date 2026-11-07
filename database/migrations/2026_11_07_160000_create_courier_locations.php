<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuryerin yer tarixçəsi: kuryer tətbiqi hər açılanda / qayıdanda göndərir (CourierAppController::location).
 * Son yer users.location_* sütunlarında da qalır (siyahı üçün tez); admin kuryerin səhifəsində günə görə tarixçəni görür.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('courier_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('accuracy')->nullable(); // metr
            $table->timestamp('recorded_at');
            $table->index(['user_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_locations');
    }
};
