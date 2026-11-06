<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Kuryer ünvandadır" (at_address) addımı götürüldü: kuryer "Yoldayam"dan birbaşa "Təhvil verdim" edir.
 * Hazırda bu statusda olan sifarişlər "Yola çıxdı" (sent) olur; status sətri köhnə tarixçə üçün qalır, amma gizlədilir.
 */
return new class extends Migration {
    public function up(): void
    {
        $atAddress = DB::table('order_statuses')->where('code', 'at_address')->value('id');
        $sent = DB::table('order_statuses')->where('code', 'sent')->value('id');
        if (!$atAddress || !$sent) {
            return;
        }
        DB::table('orders')->where('order_status_id', $atAddress)->update(['order_status_id' => $sent]);
        DB::table('order_statuses')->where('id', $atAddress)->update(['active' => 0, 'sort_order' => 99]);
    }

    public function down(): void
    {
        DB::table('order_statuses')->where('code', 'at_address')->update(['active' => 1, 'sort_order' => 7]);
    }
};
