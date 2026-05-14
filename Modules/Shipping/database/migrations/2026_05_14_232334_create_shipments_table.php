<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('delivery_providers')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('shipping_services')->restrictOnDelete();
            $table->string('exit_code', 32)->nullable()->unique();
            $table->string('confirmation_code', 32)->unique();
            $table->string('status', 32);
            $table->string('delivery_mode', 32);
            $table->timestamp('pickup_verified_at')->nullable();
            $table->timestamp('delivery_verified_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'shop_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
