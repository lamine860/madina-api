<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index GIN avec jsonb_path_ops : adapté aux recherches par égalité de chemins (@>, ?, etc.) sur PostgreSQL.
 *
 * @see https://www.postgresql.org/docs/current/datatype-json.html
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE product_variants ALTER COLUMN attributes TYPE jsonb USING attributes::jsonb');

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS product_variants_attributes_gin_idx
            ON product_variants
            USING GIN (attributes jsonb_path_ops)
            SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS product_variants_attributes_gin_idx');
    }
};
