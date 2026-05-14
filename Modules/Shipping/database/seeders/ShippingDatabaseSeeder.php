<?php

declare(strict_types=1);

namespace Modules\Shipping\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Shipping\Enums\DeliveryProviderType;
use Modules\Shipping\Enums\PayoutTrigger;

final class ShippingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('delivery_zones')->delete();
        DB::table('delivery_providers')->delete();
        DB::table('shipping_services')->delete();

        DB::table('delivery_zones')->insert([
            [
                'name' => 'Zone A',
                'neighborhoods' => json_encode([
                    ['slug' => 'madina', 'label' => 'Madina'],
                    ['slug' => 'dixinn', 'label' => 'Dixinn'],
                    ['slug' => 'matam', 'label' => 'Matam'],
                    ['slug' => 'ratoma-centre', 'label' => 'Ratoma Centre'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Zone B',
                'neighborhoods' => json_encode([
                    ['slug' => 'koloma', 'label' => 'Koloma'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('delivery_providers')->insert([
            [
                'name' => 'Kilora Internal',
                'type' => DeliveryProviderType::Internal->value,
                'payout_trigger' => PayoutTrigger::Pickup->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Boutique (auto-livraison)',
                'type' => DeliveryProviderType::Shop->value,
                'payout_trigger' => PayoutTrigger::Delivery->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('shipping_services')->insert([
            [
                'code' => 'FLASH',
                'name' => 'Kilora Flash',
                'base_price' => '50000.00',
                'description' => 'Livraison express Kilora',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'DIRECT',
                'name' => 'Kilora Direct',
                'base_price' => '35000.00',
                'description' => 'Livraison < 12h',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ECO',
                'name' => 'Kilora Eco',
                'base_price' => '20000.00',
                'description' => 'Livraison < 72h',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
