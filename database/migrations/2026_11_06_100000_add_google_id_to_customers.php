<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Google ilə giriş: müştərinin bağlı Google hesabının ID-si (sub) */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'google_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('google_id', 64)->nullable()->unique()->after('password');
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
