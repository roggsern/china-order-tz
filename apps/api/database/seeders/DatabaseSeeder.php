<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DemoProductImageSeeder::class,
            RoleSeeder::class,
            AdminSeeder::class,
            StoreSeeder::class,
            TzStoreCategorySeeder::class,
            PosPaymentMethodSeeder::class,
            ReturnReasonSeeder::class,
            CommerceChannelSeeder::class,
            ProductTypeSeeder::class,
            DepartmentSeeder::class,
            CategorySeeder::class,
            SubcategorySeeder::class,
            CatalogProductTypeSeeder::class,
            CatalogAttributeSeeder::class,
            BrandSeeder::class,
            SupplierSeeder::class,
            ShippingMethodSeeder::class,
            ProductCoreSeeder::class,
            ProductMediaSeeder::class,
            CatalogProductAttributeValueSeeder::class,
            ProductVariantSeeder::class,
            VariantPriceSeeder::class,
            VariantInventorySeeder::class,
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
            ProductShippingOptionSeeder::class,
            Iphone16ProDemoSeeder::class,
            CommerceEngineTestSeeder::class,
            EcommerceSeeder::class,
            NotificationTemplateSeeder::class,
            CustomerTagSeeder::class,
            LoyaltySeeder::class,
            GrowthSeeder::class,
            ActivityLogSeeder::class,
            ReturnRequestSeeder::class,
            AnalyticsDemoSeeder::class,
            CmsDefaultNavigationShellSeeder::class,
        ]);
    }
}
