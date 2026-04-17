<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['sku']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('sku');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX product_variants_sku_active_unique ON product_variants (sku) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS product_variants_sku_active_unique');
        }

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['sku']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique('sku');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
