<?php

namespace Tests\Feature\Stores;

use App\Enums\ActivityEventType;
use App\Enums\StoreAssignmentType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use App\Services\Pos\Receipt\StoreReceiptSettings;
use App\Services\Stores\StoreService;
use App\Services\Stores\StoreSettingsResolver;
use App\Services\Stores\StoreSettingsService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createStore(array $settings = []): Store
    {
        return app(StoreService::class)->create([
            'code' => 'ZION',
            'name' => 'Zion Store',
            'is_active' => true,
            'settings' => $settings,
        ]);
    }

    private function adminWithStoreAccess(Store $store, array $permissions): Admin
    {
        $admin = Admin::factory()->withPermissions($permissions)->create();

        StoreUserAssignment::query()->updateOrCreate(
            [
                'admin_id' => $admin->id,
                'store_id' => $store->id,
            ],
            [
                'assignment_type' => StoreAssignmentType::Permanent,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ],
        );

        return $admin;
    }

    public function test_guest_and_customer_cannot_access_store_settings(): void
    {
        $store = $this->createStore();

        $this->getJson("/api/v1/admin/stores/{$store->id}/settings")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/admin/stores/{$store->id}/settings")->assertUnauthorized();
        $this->putJson("/api/v1/admin/stores/{$store->id}/settings", [
            'business' => ['display_name' => 'Zion'],
        ])->assertUnauthorized();
    }

    public function test_permission_denied_without_stores_permissions(): void
    {
        $store = $this->createStore();
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson("/api/v1/admin/stores/{$store->id}/settings")->assertForbidden();
        $this->putJson("/api/v1/admin/stores/{$store->id}/settings", [
            'business' => ['display_name' => 'Zion'],
        ])->assertForbidden();
    }

    public function test_view_permission_can_read_but_not_update(): void
    {
        $store = $this->createStore();
        Sanctum::actingAs(
            $this->adminWithStoreAccess($store, [AdminPermissions::STORES_VIEW]),
        );

        $this->getJson("/api/v1/admin/stores/{$store->id}/settings")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store_id', $store->id)
            ->assertJsonPath('data.business.display_name', '')
            ->assertJsonPath('data.receipt.show_logo', true);

        $this->putJson("/api/v1/admin/stores/{$store->id}/settings", [
            'business' => ['display_name' => 'Zion Retail'],
        ])->assertForbidden();
    }

    public function test_manage_updates_settings_merges_json_and_writes_audit(): void
    {
        $store = $this->createStore([
            'receipt' => [
                'footer_message' => 'Old footer',
                'thank_you_message' => 'Asante!',
                'return_policy' => '7 days',
                'address' => 'Dar es Salaam',
            ],
            'legacy_note' => 'keep-me',
        ]);

        $admin = $this->adminWithStoreAccess($store, [
            AdminPermissions::STORES_VIEW,
            AdminPermissions::STORES_MANAGE,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/admin/stores/{$store->id}/settings", [
            'business' => [
                'display_name' => 'Zion Retail',
                'phone' => '+255700000000',
                'email' => 'zion@example.com',
                'address' => 'Samora Ave',
            ],
            'receipt' => [
                'footer_message' => 'Karibu tena',
                'show_logo' => false,
            ],
            'customer' => [
                'support_phone' => '+255711111111',
                'support_email' => 'support@zion.test',
            ],
            'social' => [
                'instagram' => '@zion',
                'facebook' => 'zion.tz',
                'tiktok' => '@ziontz',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.business.display_name', 'Zion Retail')
            ->assertJsonPath('data.receipt.footer_message', 'Karibu tena')
            ->assertJsonPath('data.receipt.show_logo', false)
            ->assertJsonPath('data.customer.support_email', 'support@zion.test')
            ->assertJsonPath('data.social.instagram', '@zion');

        $store->refresh();
        $settings = $store->settings;

        $this->assertSame('Zion Retail', $settings['business']['display_name'] ?? null);
        $this->assertSame('Karibu tena', $settings['receipt']['footer_message'] ?? null);
        $this->assertFalse((bool) ($settings['receipt']['show_logo'] ?? true));
        // Existing receipt keys preserved (JSON merge).
        $this->assertSame('Asante!', $settings['receipt']['thank_you_message'] ?? null);
        $this->assertSame('7 days', $settings['receipt']['return_policy'] ?? null);
        $this->assertSame('Dar es Salaam', $settings['receipt']['address'] ?? null);
        $this->assertSame('keep-me', $settings['legacy_note'] ?? null);

        $receipt = app(StoreReceiptSettings::class)->forStore($store);
        $this->assertSame('Karibu tena', $receipt['footer_message']);
        $this->assertSame('Asante!', $receipt['thank_you_message']);
        $this->assertSame('Dar es Salaam', $receipt['address']);

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::StoreSettingsUpdated->value,
            'actor_id' => $admin->id,
            'subject_id' => $store->id,
        ]);

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::StoreSettingsUpdated->value)
            ->where('subject_id', $store->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('Old footer', $log->old_values['receipt']['footer_message'] ?? null);
        $this->assertSame('Karibu tena', $log->new_values['receipt']['footer_message'] ?? null);
        $this->assertSame('Zion Retail', $log->new_values['business']['display_name'] ?? null);
    }

    public function test_rejects_secrets_and_unknown_keys(): void
    {
        $store = $this->createStore();
        Sanctum::actingAs(
            $this->adminWithStoreAccess($store, [
                AdminPermissions::STORES_VIEW,
                AdminPermissions::STORES_MANAGE,
            ]),
        );

        $this->putJson("/api/v1/admin/stores/{$store->id}/settings", [
            'business' => ['display_name' => 'Zion'],
            'api_key' => 'secret',
        ])->assertStatus(422);

        $this->putJson("/api/v1/admin/stores/{$store->id}/settings", [
            'business' => [
                'display_name' => 'Zion',
                'password' => 'nope',
            ],
        ])->assertStatus(422);

        $this->putJson("/api/v1/admin/stores/{$store->id}/settings", [
            'business' => [
                'tax_pin' => '123',
            ],
        ])->assertStatus(422);
    }

    public function test_resolver_and_service_merge_helpers(): void
    {
        $store = $this->createStore([
            'receipt' => [
                'thank_you_message' => 'Thanks',
                'footer_message' => 'Old',
            ],
        ]);

        $resolver = app(StoreSettingsResolver::class);
        $service = app(StoreSettingsService::class);

        $merged = $service->mergeSections($store->settings ?? [], [
            'receipt' => ['footer_message' => 'New', 'show_logo' => true],
            'business' => ['display_name' => 'Zion'],
        ]);

        $this->assertSame('New', $merged['receipt']['footer_message']);
        $this->assertSame('Thanks', $merged['receipt']['thank_you_message']);
        $this->assertSame('Zion', $merged['business']['display_name']);

        $resolved = $resolver->resolve($store);
        $this->assertSame('', $resolved['business']['display_name']);
        $this->assertSame('Old', $resolved['receipt']['footer_message']);
    }
}
