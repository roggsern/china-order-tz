<?php

namespace Tests\Feature\Http;

use App\Models\Admin;
use App\Models\Product;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API Contract v1 foundation — additive success/code fields on exception renders.
 */
class ApiContractFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    public function test_unauthenticated_api_response_includes_success_and_code(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonStructure(['success', 'code', 'message']);
    }

    public function test_validation_api_response_keeps_errors_and_adds_contract_fields(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure([
                'success',
                'code',
                'message',
                'errors' => [
                    'email',
                    'password',
                ],
            ]);
    }

    public function test_forbidden_api_response_includes_success_and_code(): void
    {
        $admin = Admin::factory()->create([
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        Sanctum::actingAs($admin, ['*'], 'sanctum');

        // Admin without catalog permission → abort(403) via admin.permission middleware.
        $this->getJson('/api/v1/admin/brands')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_feature_disabled_api_response_keeps_feature_and_adds_success(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->getJson('/api/v1/wishlist')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'feature_disabled')
            ->assertJsonPath('feature', 'wishlist')
            ->assertJsonStructure(['success', 'code', 'feature', 'message']);

        $this->postJson('/api/v1/wishlist/items', [
            'product_id' => $product->id,
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'feature_disabled');
    }

    public function test_api_response_helper_builds_success_and_error_envelopes(): void
    {
        $success = ApiResponse::success(['ok' => true], 'Done', ['page' => 1])->getData(true);
        $this->assertTrue($success['success']);
        $this->assertSame('Done', $success['message']);
        $this->assertSame(['ok' => true], $success['data']);
        $this->assertSame(['page' => 1], $success['meta']);

        $withExtra = ApiResponse::success(
            data: ['id' => 1],
            message: 'Login successful',
            extra: ['token' => 'abc', 'token_type' => 'Bearer'],
        )->getData(true);
        $this->assertSame('abc', $withExtra['token']);
        $this->assertSame('Bearer', $withExtra['token_type']);
        $this->assertSame(['id' => 1], $withExtra['data']);

        $error = ApiResponse::error('Nope', 'forbidden', 403)->getData(true);
        $this->assertFalse($error['success']);
        $this->assertSame('forbidden', $error['code']);
        $this->assertSame('Nope', $error['message']);

        $validation = ApiResponse::validationError('Invalid', ['email' => ['Required.']])->getData(true);
        $this->assertFalse($validation['success']);
        $this->assertSame('validation_failed', $validation['code']);
        $this->assertSame(['email' => ['Required.']], $validation['errors']);
    }

    public function test_api_response_includes_request_id_when_present_on_request(): void
    {
        $request = Request::create('/api/v1/cart', 'GET');
        $request->attributes->set('request_id', '11111111-1111-1111-1111-111111111111');
        $this->app->instance('request', $request);

        $payload = ApiResponse::error('Unauthenticated.', 'unauthenticated', 401)->getData(true);

        $this->assertSame('11111111-1111-1111-1111-111111111111', $payload['request_id']);
    }
}
