<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes invented/demo taxonomy and hides fake catalog products from the storefront.
 * Does not delete products or orders.
 */
class CleanDemoCatalogTaxonomyCommand extends Command
{
    protected $signature = 'catalog:clean-demo-taxonomy
                            {--dry-run : Show what would change without writing}
                            {--keep-commerce-fixtures : Keep iphone-16-pro and wireless-earbuds-pro as non-demo}';

    protected $description = 'Soft-delete demo/invented categories and brands; mark fake products as demo. Leaves the taxonomy engine empty for Catalog Bible import.';

    /** Commerce fixtures useful for checkout/payment testing. */
    private const COMMERCE_FIXTURE_SLUGS = [
        'iphone-16-pro',
        'wireless-earbuds-pro',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $keepFixtures = (bool) $this->option('keep-commerce-fixtures');

        $categoryCount = Category::query()->count();
        $brandCount = Brand::query()->count();
        $pivotCount = DB::table('brand_category')->count();
        $productCount = Product::query()->count();

        $fixtureQuery = Product::query()->whereIn('slug', self::COMMERCE_FIXTURE_SLUGS);
        $fixtureCount = (clone $fixtureQuery)->count();

        $productsToMarkDemo = Product::query()
            ->when($keepFixtures, fn ($q) => $q->whereNotIn('slug', self::COMMERCE_FIXTURE_SLUGS))
            ->where('is_demo', false)
            ->count();

        $this->info('Catalog cleanup plan');
        $this->line("  Categories to soft-delete: {$categoryCount}");
        $this->line("  Brands to soft-delete: {$brandCount}");
        $this->line("  brand_category links to clear: {$pivotCount}");
        $this->line("  Products total (kept): {$productCount}");
        $this->line("  Products to mark is_demo=true: {$productsToMarkDemo}");
        if ($keepFixtures) {
            $this->line("  Commerce fixtures kept as non-demo: {$fixtureCount}");
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($keepFixtures) {
            DB::table('brand_category')->delete();

            Product::query()
                ->when($keepFixtures, fn ($q) => $q->whereNotIn('slug', self::COMMERCE_FIXTURE_SLUGS))
                ->where('is_demo', false)
                ->update(['is_demo' => true]);

            // Soft-delete all taxonomy nodes (products/orders retained; category_id stays for history).
            Category::query()->orderByDesc('id')->each(function (Category $category) {
                $category->delete();
            });

            Brand::query()->orderByDesc('id')->each(function (Brand $brand) {
                $brand->delete();
            });
        });

        $this->info('Cleanup complete.');
        $this->line('  Active categories: '.Category::query()->count());
        $this->line('  Active brands: '.Brand::query()->count());
        $this->line('  Storefront-visible products (is_demo=false): '.Product::query()->real()->count());
        $this->line('  Demo-hidden products: '.Product::query()->where('is_demo', true)->count());

        return self::SUCCESS;
    }
}
