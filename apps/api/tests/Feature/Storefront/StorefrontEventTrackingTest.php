<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorefrontEventTrackingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{visitor_uuid: string, session_id: string, visitor_id: string}
     */
    private function createIdentity(): array
    {
        $visitorUuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

        $response = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
        ])->assertOk();

        return [
            'visitor_uuid' => $visitorUuid,
            'session_id' => $response->json('data.session_id'),
            'visitor_id' => $response->json('data.visitor_id'),
        ];
    }

    public function test_records_page_view_event_with_visitor_and_session(): void
    {
        $identity = $this->createIdentity();

        $response = $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'page_view',
            'path' => '/products',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('storefront_events', [
            'id' => $response->json('data.id'),
            'visitor_id' => $identity['visitor_id'],
            'session_id' => $identity['session_id'],
            'event_type' => 'page_view',
            'path' => '/products',
            'user_id' => null,
        ]);
    }

    public function test_attaches_authenticated_user_to_event(): void
    {
        $user = User::factory()->create();
        $identity = $this->createIdentity();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'page_view',
            'path' => '/account',
        ])->assertCreated();

        $this->assertDatabaseHas('storefront_events', [
            'visitor_id' => $identity['visitor_id'],
            'session_id' => $identity['session_id'],
            'user_id' => $user->id,
            'event_type' => 'page_view',
        ]);
    }

    public function test_records_product_viewed_with_product_id(): void
    {
        $identity = $this->createIdentity();
        $product = Product::factory()->create();

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'product_viewed',
            'path' => '/products/'.$product->slug,
            'product_id' => $product->id,
        ])->assertCreated();

        $this->assertDatabaseHas('storefront_events', [
            'event_type' => 'product_viewed',
            'product_id' => $product->id,
        ]);
    }

    public function test_rejects_unknown_event_type(): void
    {
        $identity = $this->createIdentity();

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'checkout_completed',
            'path' => '/checkout',
        ])->assertUnprocessable();
    }

    public function test_strips_sensitive_metadata_before_persisting(): void
    {
        $identity = $this->createIdentity();

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'search_performed',
            'path' => '/products',
            'metadata' => [
                'query' => 'iphone',
                'email' => 'secret@example.com',
                'payment' => ['card_number' => '4111111111111111'],
            ],
        ])->assertCreated();

        $event = StorefrontEvent::query()->firstOrFail();
        $this->assertSame(['query' => 'iphone'], $event->metadata);
    }

    public function test_rejects_unknown_product_id(): void
    {
        $identity = $this->createIdentity();

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'product_viewed',
            'product_id' => '99999999-9999-4999-8999-999999999999',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Unknown product_id for storefront event.');
    }

    public function test_supports_search_and_add_to_cart_event_types(): void
    {
        $identity = $this->createIdentity();
        $category = Category::factory()->create();

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'search_performed',
            'path' => '/products',
            'metadata' => ['query' => 'shoes'],
        ])->assertCreated();

        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => $identity['visitor_uuid'],
            'session_id' => $identity['session_id'],
            'event_type' => 'add_to_cart',
            'path' => '/products/'.$product->slug,
            'product_id' => $product->id,
            'category_id' => $category->id,
            'metadata' => ['quantity' => 2],
        ])->assertCreated();

        $this->assertSame(2, StorefrontEvent::query()->count());
    }
}
