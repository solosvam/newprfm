<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin bildirişləri (zəng işarəsi): bir kliklə sifariş, hissə-hissə müraciəti, sayt sifarişi.
 * Eyni hadisəyə ikinci bildiriş yazılmır (type + subject unikal). Oxunma — əməkdaş üzrə son görülən id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                   // one_click | credit | site_order
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->string('title', 150);
            $table->string('body', 255)->nullable();
            $table->string('url', 255)->nullable();       // nisbi admin keçidi
            $table->timestamp('created_at');
            $table->unique(['type', 'subject_type', 'subject_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('notifications_seen_id')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notifications_seen_id'));
        Schema::dropIfExists('admin_notifications');
    }
};
