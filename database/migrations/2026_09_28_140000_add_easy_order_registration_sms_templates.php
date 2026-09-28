<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Phone-only registrations need a nullable email; keep its existing column type.
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['customers', 'email']
        );
        if (!$column) {
            throw new RuntimeException('customers.email sütunu tapılmadı.');
        }
        DB::statement("ALTER TABLE customers MODIFY `email` {$column->COLUMN_TYPE} NULL");

        DB::table('sms_templates')->updateOrInsert(
            ['code' => 'easy_order_registration_bonus'],
            [
                'name' => 'Asan sifariş — qeydiyyat və bonus',
                'template' => 'Parfumshop.az-da hesabiniz yaradildi. {bonus} AZN bonus qazandiniz! Mobil nomrenizle daxil olun, SMS kodu ile sifre teyin edin.',
                'active' => 1,
            ]
        );
        DB::table('sms_templates')->updateOrInsert(
            ['code' => 'easy_order_registration'],
            [
                'name' => 'Asan sifariş — qeydiyyat (bonussuz)',
                'template' => 'Parfumshop.az-da hesabiniz yaradildi. Mobil nomrenizle daxil olun ve SMS kodu ile sifrenizi teyin edin.',
                'active' => 1,
            ]
        );
    }

    public function down(): void
    {
        DB::table('sms_templates')->whereIn('code', [
            'easy_order_registration_bonus', 'easy_order_registration',
        ])->delete();
        // Do not restore email NOT NULL while phone-only accounts may exist.
    }
};
