<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Opt-in demo and transactional test data for local development.
 *
 * Creates sample carts, orders, payments, fulfillments, shipments, analytics rows,
 * and related graphs. Run only when RUN_DEMO_SEEDS=true — never on production boot.
 *
 * Preserve call order: downstream seeders depend on upstream demo records.
 */
class DemoDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CartSeeder::class,
            CheckoutSessionSeeder::class,
            OrderEngineSeeder::class,
            PaymentTransactionSeeder::class,
            FulfillmentSeeder::class,
            WarehouseJobSeeder::class,
            DeliveryOptionSeeder::class,
            ShipmentSeeder::class,
            ShipmentTrackingEventSeeder::class,
            ProductSeeder::class,
            EcommerceSeeder::class,
            AnalyticsDemoSeeder::class,
            ActivityLogSeeder::class,
            ReturnRequestSeeder::class,
            GrowthSeeder::class,
        ]);
    }
}
