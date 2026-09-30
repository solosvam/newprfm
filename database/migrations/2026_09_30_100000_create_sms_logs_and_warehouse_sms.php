<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anbar SMS bildirişləri (Telegram əvəzinə) + göndərilən bildirişlərin jurnalı.
 * OTP kodları jurnala yazılmır — yalnız kontekstli (anbar və s.) bildirişlər.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('context', 40)->index();           // warehouse_request, warehouse_selected, ...
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('msisdn', 20)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 12);                      // sent | failed
            $table->string('provider_id', 40)->nullable();     // lsim transaction id
            $table->string('error', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        // Latın hərfləri: ə/ş/ç olsa 1 SMS 160 yox, 70 simvol olur
        $templates = [
            'warehouse_request' => ['Anbar — yeni sorğu', 'Parfumshop: {count} mehsul ucun sorgu var. Movcudlugu ve qiymeti yazin: {link}'],
            'warehouse_selected' => ['Anbar — məhsul seçildi (rezerv)', 'Parfumshop: {product} x{quantity} sizden secildi. Rezerv edib tesdiqleyin: {link}'],
            'warehouse_cancelled' => ['Anbar — seçim ləğv edildi', 'Parfumshop: {product} x{quantity} secimi legv edildi, rezerv lazim deyil.'],
        ];
        foreach ($templates as $code => [$name, $template]) {
            DB::table('sms_templates')->updateOrInsert(['code' => $code], ['name' => $name, 'template' => $template, 'active' => 1]);
        }
    }

    public function down(): void
    {
        DB::table('sms_templates')->whereIn('code', ['warehouse_request', 'warehouse_selected', 'warehouse_cancelled'])->delete();
        Schema::dropIfExists('sms_logs');
    }
};
