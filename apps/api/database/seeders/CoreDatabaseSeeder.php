<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Infrastructure, configuration, and catalog scaffold required for local development.
 *
 * Safe to run on every local container boot. Does not create demo carts, orders,
 * fulfillments, or other transactional test graphs — use DemoDatabaseSeeder for that.
 */
class CoreDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DemoProductImageSeeder::class,
            RoleSeeder::class,
            AdminPermissionSeeder::class,
            AdminSeeder::class,
            SettingsSeeder::class,
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
            ProductShippingOptionSeeder::class,
            Iphone16ProDemoSeeder::class,
            CommerceEngineTestSeeder::class,
            NotificationTemplateSeeder::class,
            CustomerTagSeeder::class,
            LoyaltySeeder::class,
            CmsDefaultNavigationShellSeeder::class,
        ]);
    }
}
