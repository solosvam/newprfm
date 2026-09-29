<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name_az', 150);
            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouse_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('created_by'); // users; legacy user ID types vary.
            $table->timestamps();
        });

        Schema::create('warehouse_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('requested_quantity');
            $table->timestamps();
            $table->unique(['warehouse_request_id', 'order_item_id'], 'warehouse_request_item_unique');
        });

        // Responses are append-only: correcting an answer must preserve its history.
        Schema::create('warehouse_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_request_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('available_quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('source', 20);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();
        });

        Schema::create('order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_offer_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->string('status', 30)->default('selected');
            $table->uuid('idempotency_key')->unique();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['order_item_id', 'status']);
        });

        Schema::create('allocation_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_allocation_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->unsignedBigInteger('user_id');
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_status_logs');
        Schema::dropIfExists('order_item_allocations');
        Schema::dropIfExists('warehouse_offers');
        Schema::dropIfExists('warehouse_request_items');
        Schema::dropIfExists('warehouse_requests');
        Schema::dropIfExists('warehouses');
    }
};
