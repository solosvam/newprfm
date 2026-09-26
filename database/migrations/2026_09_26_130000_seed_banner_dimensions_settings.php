<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULTS = [
        'banner_web_top_width' => 1920,
        'banner_web_top_height' => 370,
        'banner_web_bottom_width' => 1920,
        'banner_web_bottom_height' => 300,
        'banner_mobile_top_width' => 790,
        'banner_mobile_top_height' => 300,
        'banner_mobile_bottom_width' => 790,
        'banner_mobile_bottom_height' => 220,
    ];

    public function up(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'value' => (string) $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
