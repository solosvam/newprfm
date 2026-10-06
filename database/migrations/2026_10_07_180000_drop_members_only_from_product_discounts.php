<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Kimlər görsün" ayarı ləğv olunub: məhsul endirimini hamı (qonaqlar da) görür.
 * Yaratma migrasiyasında artıq sütun yoxdur — bu yalnız sütunu əvvəlcədən yaradılmış bazalar üçündür.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('product_discounts', 'members_only')) {
            Schema::table('product_discounts', fn (Blueprint $table) => $table->dropColumn('members_only'));
        }
    }

    public function down(): void
    {
        // geri qaytarılmır: yaratma migrasiyası sütunsuzdur
    }
};
