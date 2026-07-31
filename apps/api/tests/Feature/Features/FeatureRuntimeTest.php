<?php

namespace Tests\Feature\Features;

use App\Models\Admin;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ConfigurationHealth\ConfigurationHealthService;
use App\Services\Settings\SettingsService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    public function test_public_features_endpoint_is_available_without_auth(): void
    {
        $this->getJson('/api/v1/features/public')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wishlist', false)
            ->assertJsonPath('data.reviews', false)
            ->assertJsonPath('data.new_checkout', false)
            ->assertJsonMissingPath('data.maintenance_mode')
            ->assertJsonMissingPath('data.payment_verification');
    }

    public function test_public_features_reflect_settings(): void
    {
        $this->setFeatureFlags([
            'wishlist' => true,
            'reviews' => false,
            'new_checkout' => true,
        ]);

        $this->getJson('/api/v1/features/public')
            ->assertOk()
            ->assertJsonPath('data.wishlist', true)
            ->assertJsonPath('data.reviews', false)
            ->assertJsonPath('data.new_checkout', true);
    }

    public function test_disabled_wishlist_blocks_customer_api(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->getJson('/api/v1/wishlist')
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('feature', 'wishlist');

        $this->postJson('/api/v1/wishlist/items', [
            'product_id' => $product->id,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_disabled');

        $this->deleteJson('/api/v1/wishlist/items/'.$product->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_disabled');
    }

    public function test_enabled_wishlist_allows_customer_crud(): void
    {
        $this->setFeatureFlags(['wishlist' => true]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create();

        $this->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/wishlist/items', [
            'product_id' => $product->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.product_id', $product->id);

        $this->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson('/api/v1/wishlist/items/'.$product->id)
            ->assertOk();

        $this->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_disabled_reviews_blocks_product_review_endpoints(): void
    {
        $product = Product::factory()->create(['slug' => 'blocked-reviews']);

        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('feature', 'reviews');

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/products/'.$product->slug.'/reviews', [
            'rating' => 5,
            'comment' => 'Great product',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'feature_disabled');
    }

    public function test_enabled_reviews_allow_list_and_submit(): void
    {
        $this->setFeatureFlags(['reviews' => true]);

        $product = Product::factory()->create(['slug' => 'reviewable-product']);
        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 5,
            'is_approved' => true,
            'comment' => 'Approved review',
        ]);
        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 1,
            'is_approved' => false,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/products/'.$product->slug.'/reviews', [
            'rating' => 4,
            'title' => 'Solid',
            'comment' => 'Works well for daily use.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4);
    }

    public function test_product_detail_omits_review_aggregates_when_reviews_disabled(): void
    {
        $product = Product::factory()->fromChina()->create(['slug' => 'no-reviews-runtime']);
        Review::factory()->create([
            'product_id' => $product->id,
            'rating' => 5,
            'is_approved' => true,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.review_count', 0);

        $this->setFeatureFlags(['reviews' => true]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.average_rating', 5)
            ->assertJsonPath('data.review_count', 1);
    }

    public function test_configuration_health_reports_feature_runtime(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::SETTINGS_VIEW])->create(),
        );

        $this->setFeatureFlags(['wishlist' => true]);

        $response = $this->getJson('/api/v1/admin/configuration-health')->assertOk();

        $featureChecks = collect($response->json('data.checks'))
            ->where('group', 'features')
            ->pluck('message')
            ->all();

        $this->assertTrue(
            collect($featureChecks)->contains(
                fn (string $message) => str_contains($message, 'Feature runtime connected'),
            ),
        );
        $this->assertTrue(
            collect($featureChecks)->contains(
                fn (string $message) => str_contains($message, 'enabled but has no recorded usage'),
            ),
        );
    }

    public function test_configuration_health_service_includes_runtime_connected_message(): void
    {
        $report = app(ConfigurationHealthService::class)->report();

        $this->assertTrue(
            collect($report['checks'])->contains(
                fn (array $check) => $check['group'] === 'features'
                    && str_contains($check['message'], 'Feature runtime connected'),
            ),
        );
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function setFeatureFlags(array $flags): void
    {
        app(SettingsService::class)->set('features.flags', array_merge([
            'wishlist' => false,
            'reviews' => false,
            'new_checkout' => false,
        ], $flags));
        Cache::flush();
    }
}
