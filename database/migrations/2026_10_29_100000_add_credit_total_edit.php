<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM: operator kreditin ümumi məbləğini kampaniya üçün azalda bilər (məs. 6 ay faizsiz).
 * standard_total — müddətin faizi ilə hesablanan məbləğ; total — razılaşdırılan; total_edited_by — kim dəyişib (dəyişməyibsə null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->decimal('standard_total', 10, 2)->nullable()->after('total');
            $table->unsignedBigInteger('total_edited_by')->nullable()->after('standard_total');
        });

        DB::table('permissions')->updateOrInsert(
            ['name' => 'credit_total_edit', 'guard_name' => 'admin'],
            ['description' => 'CRM: kreditin ümumi məbləğini əl ilə dəyişmək (kampaniya)', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'credit_total_edit')->where('guard_name', 'admin')->delete();
        Schema::table('credit_applications', fn (Blueprint $table) => $table->dropColumn(['standard_total', 'total_edited_by']));
    }
};
