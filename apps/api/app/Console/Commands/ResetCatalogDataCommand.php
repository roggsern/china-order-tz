<?php

namespace App\Console\Commands;

use App\Services\Catalog\ResetCatalogDataService;
use Illuminate\Console\Command;

class ResetCatalogDataCommand extends Command
{
    protected $signature = 'app:reset-catalog-data
                            {--force : Skip the confirmation prompt (testing/automation only)}';

    protected $description = 'Delete product catalog instance data for local development QA cleanup';

    public function handle(ResetCatalogDataService $service): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }

        $this->warn('This will permanently delete product catalog instance data:');
        $this->line('  products, variants, media, prices, inventory, reviews, notifications, and related product records.');
        $this->newLine();
        $this->line('Product types, catalog attributes, categories, brands, stores, suppliers, shipping configuration, users, admins, roles, permissions, and settings will be preserved.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Delete all product catalog data?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $result = $service->handle();
        $deleted = $result['deleted'];

        $this->newLine();
        $this->info('Deleted:');
        $this->line('Products: '.$deleted['products']);
        $this->line('Variants: '.$deleted['variants']);
        $this->line('Media: '.$deleted['media']);
        $this->line('Prices: '.$deleted['prices']);
        $this->line('Inventory records: '.$deleted['inventory_records']);
        $this->line('Notifications: '.$deleted['notifications']);
        $this->line('Reviews: '.$deleted['reviews']);

        if ($deleted['product_attribute_assignments'] > 0) {
            $this->line('Product attribute assignments: '.$deleted['product_attribute_assignments']);
        }

        if ($deleted['customer_product_links'] > 0) {
            $this->line('Cart / wishlist items: '.$deleted['customer_product_links']);
        }

        $this->newLine();
        $this->info('Preserved:');
        $this->line('Product Types');
        $this->line('Attributes');
        $this->line('Categories');
        $this->line('Brands');
        $this->line('Stores');
        $this->line('Suppliers');
        $this->line('Shipping configuration');
        $this->line('Users');

        $remaining = $result['remaining'];
        $this->newLine();

        if ($remaining === []) {
            $this->info('No remaining product rows detected in core catalog tables.');
        } else {
            $this->warn('Remaining product rows (investigate if unexpected):');
            foreach ($remaining as $table => $count) {
                $this->line(sprintf('%s: %d', $table, $count));
            }
        }

        $this->newLine();
        $this->info('Catalog reset completed.');

        return self::SUCCESS;
    }
}
