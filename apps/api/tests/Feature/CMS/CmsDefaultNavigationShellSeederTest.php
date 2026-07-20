<?php

namespace Tests\Feature\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationResolver;
use App\Services\CMS\CmsNavigationShellService;
use Database\Seeders\CmsDefaultNavigationShellSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsDefaultNavigationShellSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);
        $shellCount = CmsNavigationShell::query()->count();
        $itemCount = CmsNavigationItem::query()->count();

        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $this->assertSame($shellCount, CmsNavigationShell::query()->count());
        $this->assertSame($itemCount, CmsNavigationItem::query()->count());
        $this->assertSame(10, $shellCount);
    }

    public function test_only_one_default_shell_per_context_and_type(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $groups = CmsNavigationShell::query()
            ->where('is_default', true)
            ->get()
            ->groupBy(fn (CmsNavigationShell $shell) => $shell->commerce_context->value.':'.$shell->navigation_type->value);

        foreach ($groups as $slot => $shells) {
            $this->assertCount(1, $shells, "Expected one default for {$slot}");
        }

        $this->assertTrue(
            CmsNavigationShell::query()
                ->where('slug', 'default-global-primary')
                ->where('default_slot', 'GLOBAL:PRIMARY')
                ->where('status', CmsStatus::Active->value)
                ->where('is_default', true)
                ->exists(),
        );
    }

    public function test_default_shell_resolution_for_global_primary(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $shell = app(CmsNavigationShellService::class)->findDefaultShell(
            CmsCommerceContext::Global,
            CmsNavigationType::Primary,
        );

        $this->assertNotNull($shell);
        $this->assertSame('default-global-primary', $shell->slug);
        $this->assertSame(CmsStatus::Active, $shell->status);

        $resolved = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::Global,
            CmsNavigationType::Primary,
            'guest',
            hydrateMegaMenus: false,
        );

        $this->assertSame('default-global-primary', $resolved['shell']['slug']);
        $titles = collect($resolved['items'])->pluck('title')->all();
        $this->assertSame(
            ['Order from China', 'Buy from TZ', 'About Us', 'Contact Us'],
            $titles,
        );
    }

    public function test_journey_items_reference_engines_not_taxonomy_copies(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $journeys = CmsNavigationItem::query()
            ->where('item_type', CmsNavigationItemType::Journey->value)
            ->get();

        $this->assertTrue($journeys->isNotEmpty());

        foreach ($journeys as $item) {
            $this->assertNull($item->target_type);
            $this->assertContains($item->target_value, [
                CmsCommerceContext::ChinaImport->value,
                CmsCommerceContext::TzLocal->value,
            ]);
        }

        // No store/category entity IDs stored as navigation targets for journeys.
        $this->assertSame(
            0,
            CmsNavigationItem::query()
                ->where('item_type', CmsNavigationItemType::Journey->value)
                ->whereNotNull('target_type')
                ->count(),
        );

        $resolved = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::Global,
            CmsNavigationType::Primary,
            'guest',
            hydrateMegaMenus: false,
        );

        $china = collect($resolved['items'])->firstWhere('title', 'Order from China');
        $tz = collect($resolved['items'])->firstWhere('title', 'Buy from TZ');

        $this->assertSame('china_storefront_catalog', $china['journey']['engine']);
        $this->assertSame('tz_storefront_catalog', $tz['journey']['engine']);
    }

    public function test_footer_buy_from_tz_is_journey_without_store_copies(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $footer = CmsNavigationShell::query()
            ->where('slug', 'default-global-footer')
            ->with('items')
            ->firstOrFail();

        $buyFromTz = $footer->items->firstWhere('title', 'Buy From TZ');
        $this->assertNotNull($buyFromTz);
        $this->assertSame(CmsNavigationItemType::Journey, $buyFromTz->item_type);
        $this->assertSame(CmsCommerceContext::TzLocal->value, $buyFromTz->target_value);

        // Footer must not embed store slug copies as LINK children under Buy From TZ.
        $this->assertSame(0, $buyFromTz->children()->count());

        $resolved = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::Global,
            CmsNavigationType::Footer,
            'guest',
            hydrateMegaMenus: false,
        );

        $titles = collect($resolved['items'])->pluck('title')->all();
        $this->assertContains('About', $titles);
        $this->assertContains('Contact', $titles);
        $this->assertContains('Quick Links', $titles);
        $this->assertContains('Buy From TZ', $titles);
    }

    public function test_china_shell_does_not_include_tz_journey(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $shell = CmsNavigationShell::query()
            ->where('slug', 'default-china-import-primary')
            ->with('items')
            ->firstOrFail();

        $values = $shell->items
            ->where('item_type', CmsNavigationItemType::Journey)
            ->pluck('target_value')
            ->all();

        $this->assertSame([CmsCommerceContext::ChinaImport->value], array_values($values));
        $this->assertNotContains(CmsCommerceContext::TzLocal->value, $values);
    }

    public function test_guest_and_auth_visibility_on_mobile(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $guest = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::Global,
            CmsNavigationType::Mobile,
            'guest',
            hydrateMegaMenus: false,
        );
        $guestTitles = collect($guest['items'])->pluck('title')->all();
        $this->assertContains('Sign In', $guestTitles);
        $this->assertContains('Create Account', $guestTitles);
        $this->assertNotContains('My Orders', $guestTitles);
        $this->assertNotContains('My Account', $guestTitles);

        $auth = app(CmsNavigationResolver::class)->resolve(
            CmsCommerceContext::Global,
            CmsNavigationType::Mobile,
            'authenticated',
            hydrateMegaMenus: false,
        );
        $authTitles = collect($auth['items'])->pluck('title')->all();
        $this->assertContains('My Orders', $authTitles);
        $this->assertContains('My Account', $authTitles);
        $this->assertContains('Sign Out', $authTitles);
        $this->assertNotContains('Sign In', $authTitles);
    }

    public function test_duplicate_default_slot_prevention_on_reseed(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $seeded = CmsNavigationShell::query()->where('slug', 'default-global-primary')->firstOrFail();
        $seeded->forceFill([
            'is_default' => false,
            'default_slot' => null,
        ])->save();

        // Another shell temporarily owns the default slot.
        CmsNavigationShell::factory()->create([
            'slug' => 'rogue-global-primary',
            'commerce_context' => CmsCommerceContext::Global,
            'navigation_type' => CmsNavigationType::Primary,
            'status' => CmsStatus::Active,
            'is_default' => true,
            'default_slot' => 'GLOBAL:PRIMARY',
        ]);

        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $defaults = CmsNavigationShell::query()
            ->where('default_slot', 'GLOBAL:PRIMARY')
            ->get();

        $this->assertCount(1, $defaults);
        $this->assertSame('default-global-primary', $defaults->first()->slug);
        $this->assertFalse(
            (bool) CmsNavigationShell::query()->where('slug', 'rogue-global-primary')->first()?->is_default,
        );
        $this->assertNull(
            CmsNavigationShell::query()->where('slug', 'rogue-global-primary')->first()?->default_slot,
        );
    }

    public function test_no_duplicate_positions_among_root_items(): void
    {
        $this->seed(CmsDefaultNavigationShellSeeder::class);

        $shells = CmsNavigationShell::query()->with('rootItems')->get();
        foreach ($shells as $shell) {
            $positions = $shell->rootItems->pluck('position')->all();
            $this->assertSame(
                $positions,
                array_values(array_unique($positions)),
                "Duplicate root positions in shell {$shell->slug}",
            );
        }
    }
}
