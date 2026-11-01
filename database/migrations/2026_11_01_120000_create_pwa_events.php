<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sayt tətbiq kimi (ana ekrandan) — quraşdırma və açılışlar (pwa.js → /pwa/event).
 * install: Android-də brauzerin "appinstalled" hadisəsi (prompt), iPhone-da ana ekrandan ilk açılış (first_open);
 * open: ana ekrandan açılış (sessiyada bir dəfə). Cihaz — brauzerin yaddaşındakı təsadüfi id, şəxsi məlumat yoxdur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pwa_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 10);                  // install | open
            $table->string('method', 12)->nullable();     // install üçün: prompt | first_open
            $table->string('device_id', 40);
            $table->string('platform', 10);               // android | ios | other
            $table->string('os_version', 20)->nullable();
            $table->string('browser', 30)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->string('model', 60)->nullable();      // Android (Chrome); iPhone modeli vermir
            $table->string('screen', 15)->nullable();     // CSS piksel, kiçik x böyük: 390x844
            $table->decimal('dpr', 3, 1)->nullable();
            $table->string('lang', 10)->nullable();
            $table->boolean('dark')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['event', 'created_at']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pwa_events');
    }
};
