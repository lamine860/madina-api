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
        if (! Schema::hasTable('product_images')) {
            return;
        }

        if (! Schema::hasColumn('product_images', 'url')) {
            return;
        }

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('filename', 255)->nullable()->after('product_id');
        });

        foreach (DB::table('product_images')->cursor() as $row) {
            $url = (string) ($row->url ?? '');
            $filename = $url !== '' ? basename($url) : (bin2hex(random_bytes(8)).'.webp');
            DB::table('product_images')->where('id', $row->id)->update(['filename' => $filename]);
        }

        Schema::table('product_images', function (Blueprint $table) {
            if (Schema::hasColumn('product_images', 'thumbnail_url')) {
                $table->dropColumn('thumbnail_url');
            }
            $table->dropColumn('url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_images')) {
            return;
        }

        if (! Schema::hasColumn('product_images', 'filename')) {
            return;
        }

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('product_id');
        });

        foreach (DB::table('product_images')->cursor() as $row) {
            $filename = (string) ($row->filename ?? '');
            $url = $filename !== '' ? 'products/'.$row->product_id.'/original/'.$filename : '';
            DB::table('product_images')->where('id', $row->id)->update(['url' => $url]);
        }

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('filename');
        });
    }
};
