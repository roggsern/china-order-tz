<?php

namespace Tests\Feature\Storefront;

use App\Models\StorefrontSession;
use App\Models\StorefrontVisitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorefrontVisitorIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_identify_creates_visitor_and_session(): void
    {
        $visitorUuid = '11111111-1111-4111-8111-111111111111';

        $response = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['visitor_id', 'session_id', 'visitor_uuid'],
            ]);

        $this->assertSame($visitorUuid, $response->json('data.visitor_uuid'));

        $this->assertDatabaseHas('storefront_visitors', [
            'id' => $response->json('data.visitor_id'),
            'visitor_uuid' => $visitorUuid,
        ]);

        $this->assertDatabaseHas('storefront_sessions', [
            'id' => $response->json('data.session_id'),
            'visitor_id' => $response->json('data.visitor_id'),
            'user_id' => null,
            'ended_at' => null,
        ]);
    }

    public function test_identify_reuses_existing_visitor(): void
    {
        $visitorUuid = '22222222-2222-4222-8222-222222222222';

        $first = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
        ])->assertOk();

        $second = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
            'session_id' => $first->json('data.session_id'),
        ])->assertOk();

        $this->assertSame($first->json('data.visitor_id'), $second->json('data.visitor_id'));
        $this->assertSame(1, StorefrontVisitor::query()->count());
        $this->assertTrue(
            StorefrontVisitor::query()->firstOrFail()->last_seen_at->greaterThanOrEqualTo(
                StorefrontVisitor::query()->firstOrFail()->first_seen_at,
            ),
        );
    }

    public function test_identify_refreshes_existing_session_activity(): void
    {
        $visitorUuid = '33333333-3333-4333-8333-333333333333';

        $first = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
        ])->assertOk();

        $sessionId = $first->json('data.session_id');

        $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
            'session_id' => $sessionId,
        ])->assertOk()
            ->assertJsonPath('data.session_id', $sessionId);

        $this->assertSame(1, StorefrontSession::query()->count());
    }

    public function test_identify_creates_new_session_after_inactivity_timeout(): void
    {
        config(['storefront.visitor_session_timeout_minutes' => 30]);

        $visitorUuid = '44444444-4444-4444-8444-444444444444';

        $first = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
        ])->assertOk();

        $oldSession = StorefrontSession::query()->findOrFail($first->json('data.session_id'));
        $oldSession->forceFill(['last_activity_at' => now()->subMinutes(45)])->save();

        $second = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
            'session_id' => $oldSession->id,
        ])->assertOk();

        $this->assertNotSame($oldSession->id, $second->json('data.session_id'));
        $this->assertNotNull($oldSession->fresh()->ended_at);
        $this->assertSame(2, StorefrontSession::query()->count());
    }

    public function test_identify_attaches_authenticated_customer_to_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $visitorUuid = '55555555-5555-4555-8555-555555555555';

        $response = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
        ])->assertOk();

        $this->assertDatabaseHas('storefront_sessions', [
            'id' => $response->json('data.session_id'),
            'user_id' => $user->id,
        ]);
    }

    public function test_identify_merges_customer_on_subsequent_authenticated_call(): void
    {
        $user = User::factory()->create();
        $visitorUuid = '66666666-6666-4666-8666-666666666666';

        $guest = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
        ])->assertOk();

        Sanctum::actingAs($user);

        $authenticated = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => $visitorUuid,
            'session_id' => $guest->json('data.session_id'),
        ])->assertOk();

        $this->assertSame($guest->json('data.session_id'), $authenticated->json('data.session_id'));
        $this->assertDatabaseHas('storefront_sessions', [
            'id' => $guest->json('data.session_id'),
            'user_id' => $user->id,
        ]);
    }

    public function test_identify_generates_visitor_uuid_when_missing_or_invalid(): void
    {
        $response = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => 'not-a-uuid',
        ])->assertOk();

        $this->assertNotSame('not-a-uuid', $response->json('data.visitor_uuid'));
        $this->assertTrue(
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                (string) $response->json('data.visitor_uuid'),
            ) === 1,
        );
    }
}
