<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
            $table->dropUnique(['slug']);
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index('user_id');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['slug']);
            $table->dropSoftDeletes();
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->unique('user_id');
            $table->unique('slug');
        });
    }
};
