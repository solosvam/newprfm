<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_term_items', function (Blueprint $table) {
            $table->id();
            $table->string('title_az')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ru')->nullable();
            $table->longText('content_az')->nullable();
            $table->longText('content_en')->nullable();
            $table->longText('content_ru')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Köhnə textarea mətnləri silinmir; ilk qayda kimi köçürülür.
        foreach (DB::table('credit_terms')->get() as $index => $terms) {
            if (!trim((string) $terms->content_az) && !trim((string) $terms->content_en) && !trim((string) $terms->content_ru)) continue;
            DB::table('credit_term_items')->insert([
                'title_az' => 'Ümumi şərtlər', 'title_en' => 'General terms', 'title_ru' => 'Общие условия',
                'content_az' => $terms->content_az, 'content_en' => $terms->content_en, 'content_ru' => $terms->content_ru,
                'sort_order' => $index + 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_term_items');
    }
};
