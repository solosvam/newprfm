<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferrum Chrome extension-u: sifariş + müştərinin kredit məlumatlarını (FİN, vəsiqə şəkilləri) çəkir.
 * Həssas məlumat olduğu üçün ayrıca "ferrum" icazəsi və kim nəyi nə vaxt açdı jurnalı.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'ferrum', 'guard_name' => 'admin'],
            ['description' => 'Ferrum: müştərinin kredit məlumatlarını extension ilə çəkmək', 'created_at' => now(), 'updated_at' => now()]
        );

        Schema::create('sensitive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action', 40);                 // ferrum_order, ferrum_id_card_front, ...
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_access_logs');
        DB::table('permissions')->where('name', 'ferrum')->where('guard_name', 'admin')->delete();
    }
};
