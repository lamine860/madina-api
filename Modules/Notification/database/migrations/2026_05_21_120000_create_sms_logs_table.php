<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient', 20);
            $table->text('message');
            $table->string('status', 16);
            $table->string('provider', 32)->default('orange');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
