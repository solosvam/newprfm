<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('type', 30); // refund, reversal, clearing, recurring
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('bank_action_id', 191)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->text('response_text')->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'type', 'status']);
        });

        Schema::create('payment_saved_cards', function (Blueprint $table) {
            $table->id();
            // customers.id is INT in the current project.
            $table->integer('customer_id');
            $table->enum('provider', ['birbank', 'm10']);
            $table->string('provider_token_id', 191);
            $table->string('masked_pan', 32)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->unique(['provider', 'provider_token_id']);
            $table->index(['customer_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_saved_cards');
        Schema::dropIfExists('payment_operations');
    }
};
