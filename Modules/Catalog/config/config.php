<?php

use Intervention\Image\Drivers\Gd\Driver;

return [
    'name' => 'Catalog',

    /**
     * Intervention Image : driver (classe) et qualité WebP pour original / thumbs / large.
     *
     * @var array{driver: class-string, webp_quality: int}
     */
    'image' => [
        'driver' => env('CATALOG_IMAGE_DRIVER', Driver::class),
        'webp_quality' => (int) env('CATALOG_IMAGE_WEBP_QUALITY', 85),
    ],
];
