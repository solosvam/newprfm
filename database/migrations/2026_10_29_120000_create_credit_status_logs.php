<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredit müraciətinin status tarixçəsi.
 * source: manual — əməkdaş əl ilə; ferrum_fill — extension Ferrum formasını doldurdu (Yoxlanılır);
 * contract — Ferrum müqaviləsi (PDF) bağlandı (Təsdiqlənib).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_application_id');
            $table->unsignedBigInteger('from_status_id')->nullable();
            $table->unsignedBigInteger('to_status_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source', 20);
            $table->timestamp('created_at');
            $table->index('credit_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_status_logs');
    }
};
