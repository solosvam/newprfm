<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouse_access_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::table('warehouse_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('recorded_by')->nullable()->change();
            $table->foreignId('warehouse_access_link_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_access_link_id');
        });
        // Keep recorded_by nullable: historical warehouse replies have no admin actor.
        Schema::dropIfExists('warehouse_access_links');
    }
};
