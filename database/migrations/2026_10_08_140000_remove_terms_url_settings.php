<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Checkout-dakı "şərtlər" və "Kredit şərtləri" keçidləri artıq saytın öz səhifələrinə gedir
 * (front.page.terms, front.installment) — köhnə saytın URL-ləri saxlanılan ayarlar lazım deyil.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', ['order_terms_url', 'credit_terms_url'])->delete();
    }

    public function down(): void
    {
        // köhnə saytın ünvanları bərpa olunmur
    }
};
