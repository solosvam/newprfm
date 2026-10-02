<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Dostunu dəvət et" (referal):
 *  - customers.referral_code — müştərinin şəxsi dəvət kodu (ilk dəfə lazım olanda yaradılır)
 *  - customer_referrals — kim kimi dəvət edib; bonus dəvət olunanın ilk sifarişi "Təhvil verildi" olanda yazılır
 */
return new class extends Migration {
    public function up(): void
    {
        // customers.id — signed INT (köhnə sxem), ona görə FK sütunları da integer()
        if (!Schema::hasColumn('customers', 'referral_code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('referral_code', 12)->nullable()->unique()->after('source');
            });
        }

        // Əvvəlki uğursuz cəhddən qalmış ola bilər
        Schema::dropIfExists('customer_referrals');

        Schema::create('customer_referrals', function (Blueprint $table) {
            $table->id();
            $table->integer('referrer_id');
            $table->integer('invitee_id')->unique();
            // registered — qeydiyyatdan keçib; rewarded — bonus yazılıb; rejected — şərtlərə uyğun deyil
            $table->string('status', 20)->default('registered');
            $table->unsignedBigInteger('order_id')->nullable(); // bonusu yaradan ilk sifariş
            $table->decimal('referrer_amount', 10, 2)->nullable();
            $table->decimal('invitee_amount', 10, 2)->nullable();
            $table->boolean('referrer_rewardable')->default(true); // limit dolanda (no_reward rejimi) false
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('invitee_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->index(['referrer_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_referrals');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
