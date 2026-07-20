<?php

namespace Database\Seeders;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsNavigationVisibility;
use App\Enums\CMS\CmsStatus;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 5C-1 — Default CMS navigation shells.
 *
 * Mirrors apps/web navigation-policy.ts + home-data footerLinks so activating
 * CMS does not change the customer experience. Journeys reference commerce
 * engines (ChinaStorefrontCatalog / Store Engine) — no category or store copies.
 *
 * Idempotent: safe to re-run; preserves one default per (context, type).
 */
class CmsDefaultNavigationShellSeeder extends Seeder
{
    /** Labels mirrored from apps/web STOREFRONT_NAV_LABELS — do not invent. */
    private const LABEL_ORDER_FROM_CHINA = 'Order from China';

    private const LABEL_BUY_FROM_TZ = 'Buy from TZ';

    private const LABEL_MY_ORDERS = 'My Orders';

    private const LABEL_ABOUT_US = 'About Us';

    private const LABEL_CONTACT_US = 'Contact Us';

    private const LABEL_SIGN_IN = 'Sign In';

    private const LABEL_CREATE_ACCOUNT = 'Create Account';

    private const LABEL_MY_ACCOUNT = 'My Account';

    private const LABEL_SIGN_OUT = 'Sign Out';

    private const LABEL_NOTIFICATIONS = 'Notifications';

    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->shellBlueprints() as $blueprint) {
                $this->seedShell($blueprint);
            }
        });
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     commerce_context: CmsCommerceContext,
     *     navigation_type: CmsNavigationType,
     *     items: list<array<string, mixed>>
     * }>
     */
    private function shellBlueprints(): array
    {
        return [
            // —— GLOBAL (storefront chrome; web resolves commerce_context=GLOBAL) ——
            [
                'slug' => 'default-global-primary',
                'name' => 'Default Global Primary',
                'commerce_context' => CmsCommerceContext::Global,
                'navigation_type' => CmsNavigationType::Primary,
                'items' => $this->primaryItemsBothJourneys(),
            ],
            [
                'slug' => 'default-global-footer',
                'name' => 'Default Global Footer',
                'commerce_context' => CmsCommerceContext::Global,
                'navigation_type' => CmsNavigationType::Footer,
                'items' => $this->footerItemsGlobal(),
            ],
            [
                'slug' => 'default-global-mobile',
                'name' => 'Default Global Mobile',
                'commerce_context' => CmsCommerceContext::Global,
                'navigation_type' => CmsNavigationType::Mobile,
                'items' => $this->mobileItemsBothJourneys(),
            ],
            [
                'slug' => 'default-global-utility',
                'name' => 'Default Global Utility',
                'commerce_context' => CmsCommerceContext::Global,
                'navigation_type' => CmsNavigationType::Utility,
                'items' => $this->utilityItems(),
            ],

            // —— CHINA_IMPORT (no TZ journey — never mix contexts) ——
            [
                'slug' => 'default-china-import-primary',
                'name' => 'Default China Import Primary',
                'commerce_context' => CmsCommerceContext::ChinaImport,
                'navigation_type' => CmsNavigationType::Primary,
                'items' => $this->primaryItemsChinaOnly(),
            ],
            [
                'slug' => 'default-china-import-footer',
                'name' => 'Default China Import Footer',
                'commerce_context' => CmsCommerceContext::ChinaImport,
                'navigation_type' => CmsNavigationType::Footer,
                'items' => $this->footerItemsChina(),
            ],
            [
                'slug' => 'default-china-import-mobile',
                'name' => 'Default China Import Mobile',
                'commerce_context' => CmsCommerceContext::ChinaImport,
                'navigation_type' => CmsNavigationType::Mobile,
                'items' => $this->mobileItemsChinaOnly(),
            ],

            // —— TZ_LOCAL (no China journey — never mix contexts) ——
            [
                'slug' => 'default-tz-local-primary',
                'name' => 'Default TZ Local Primary',
                'commerce_context' => CmsCommerceContext::TzLocal,
                'navigation_type' => CmsNavigationType::Primary,
                'items' => $this->primaryItemsTzOnly(),
            ],
            [
                'slug' => 'default-tz-local-footer',
                'name' => 'Default TZ Local Footer',
                'commerce_context' => CmsCommerceContext::TzLocal,
                'navigation_type' => CmsNavigationType::Footer,
                'items' => $this->footerItemsTz(),
            ],
            [
                'slug' => 'default-tz-local-mobile',
                'name' => 'Default TZ Local Mobile',
                'commerce_context' => CmsCommerceContext::TzLocal,
                'navigation_type' => CmsNavigationType::Mobile,
                'items' => $this->mobileItemsTzOnly(),
            ],
        ];
    }

    /**
     * @param  array{
     *     slug: string,
     *     name: string,
     *     commerce_context: CmsCommerceContext,
     *     navigation_type: CmsNavigationType,
     *     items: list<array<string, mixed>>
     * }  $blueprint
     */
    private function seedShell(array $blueprint): void
    {
        $context = $blueprint['commerce_context'];
        $type = $blueprint['navigation_type'];
        $slot = CmsNavigationShell::defaultSlotKey($context, $type);

        // Clear any other default for this slot (idempotent uniqueness).
        CmsNavigationShell::query()
            ->where('default_slot', $slot)
            ->where('slug', '!=', $blueprint['slug'])
            ->update([
                'is_default' => false,
                'default_slot' => null,
            ]);

        $shell = CmsNavigationShell::query()->updateOrCreate(
            ['slug' => $blueprint['slug']],
            [
                'name' => $blueprint['name'],
                'commerce_context' => $context,
                'navigation_type' => $type,
                'status' => CmsStatus::Active,
                'is_default' => true,
                'default_slot' => $slot,
                'created_by' => null,
            ],
        );

        // Replace items so re-seed always mirrors current blueprint (idempotent content).
        CmsNavigationItem::query()->where('navigation_shell_id', $shell->id)->delete();

        $position = 0;
        foreach ($blueprint['items'] as $item) {
            $this->createItemTree($shell, $item, null, $position);
            $position++;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createItemTree(
        CmsNavigationShell $shell,
        array $item,
        ?string $parentId,
        int $position,
    ): CmsNavigationItem {
        $created = CmsNavigationItem::query()->create([
            'navigation_shell_id' => $shell->id,
            'parent_id' => $parentId,
            'title' => $item['title'],
            'icon' => $item['icon'] ?? null,
            'position' => $position,
            'visibility' => $item['visibility'] ?? CmsNavigationVisibility::Public,
            'item_type' => $item['item_type'],
            'target_type' => $item['target_type'] ?? null,
            'target_value' => $item['target_value'] ?? null,
            'is_enabled' => $item['is_enabled'] ?? true,
        ]);

        $childPosition = 0;
        foreach ($item['children'] ?? [] as $child) {
            $this->createItemTree($shell, $child, $created->id, $childPosition);
            $childPosition++;
        }

        return $created;
    }

    /** @return list<array<string, mixed>> */
    private function primaryItemsBothJourneys(): array
    {
        return [
            $this->journeyChina(0),
            $this->journeyTz(1),
            $this->link(self::LABEL_MY_ORDERS, '/orders', CmsNavigationVisibility::AuthOnly, 2),
            $this->link(self::LABEL_ABOUT_US, '/#about', CmsNavigationVisibility::Public, 3),
            $this->link(self::LABEL_CONTACT_US, '/#contact', CmsNavigationVisibility::Public, 4),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function primaryItemsChinaOnly(): array
    {
        return [
            $this->journeyChina(0),
            $this->link(self::LABEL_MY_ORDERS, '/orders', CmsNavigationVisibility::AuthOnly, 1),
            $this->link(self::LABEL_ABOUT_US, '/#about', CmsNavigationVisibility::Public, 2),
            $this->link(self::LABEL_CONTACT_US, '/#contact', CmsNavigationVisibility::Public, 3),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function primaryItemsTzOnly(): array
    {
        return [
            $this->journeyTz(0),
            $this->link(self::LABEL_MY_ORDERS, '/orders', CmsNavigationVisibility::AuthOnly, 1),
            $this->link(self::LABEL_ABOUT_US, '/#about', CmsNavigationVisibility::Public, 2),
            $this->link(self::LABEL_CONTACT_US, '/#contact', CmsNavigationVisibility::Public, 3),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mobileItemsBothJourneys(): array
    {
        return [
            ...$this->primaryItemsBothJourneys(),
            ...$this->mobileAuthExtras(5),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mobileItemsChinaOnly(): array
    {
        return [
            ...$this->primaryItemsChinaOnly(),
            ...$this->mobileAuthExtras(4),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mobileItemsTzOnly(): array
    {
        return [
            ...$this->primaryItemsTzOnly(),
            ...$this->mobileAuthExtras(4),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mobileAuthExtras(int $startPosition): array
    {
        return [
            $this->link(self::LABEL_SIGN_IN, '/login', CmsNavigationVisibility::GuestOnly, $startPosition),
            $this->link(self::LABEL_CREATE_ACCOUNT, '/register', CmsNavigationVisibility::GuestOnly, $startPosition + 1),
            $this->link(self::LABEL_NOTIFICATIONS, '/account/notifications', CmsNavigationVisibility::AuthOnly, $startPosition + 2),
            $this->link(self::LABEL_MY_ACCOUNT, '/account', CmsNavigationVisibility::AuthOnly, $startPosition + 3),
            $this->link(self::LABEL_SIGN_OUT, '#sign-out', CmsNavigationVisibility::AuthOnly, $startPosition + 4),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function utilityItems(): array
    {
        // Mirrors getHeaderAccountActions — guest vs auth via visibility.
        return [
            $this->link(self::LABEL_SIGN_IN, '/login', CmsNavigationVisibility::GuestOnly, 0),
            $this->link(self::LABEL_CREATE_ACCOUNT, '/register', CmsNavigationVisibility::GuestOnly, 1),
            $this->link(self::LABEL_NOTIFICATIONS, '/account/notifications', CmsNavigationVisibility::AuthOnly, 2),
            $this->link(self::LABEL_MY_ACCOUNT, '/account', CmsNavigationVisibility::AuthOnly, 3),
            $this->link(self::LABEL_SIGN_OUT, '#sign-out', CmsNavigationVisibility::AuthOnly, 4),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function footerItemsGlobal(): array
    {
        return [
            $this->footerAboutGroup(0),
            $this->footerContactGroup(1),
            $this->footerQuickLinksGroup(2, includeBothJourneys: true),
            // Live Store Engine via JOURNEY — no store name/slug copies.
            $this->journeyTz(3, title: 'Buy From TZ'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function footerItemsChina(): array
    {
        return [
            $this->footerAboutGroup(0),
            $this->footerContactGroup(1),
            $this->footerQuickLinksGroup(2, includeBothJourneys: false, chinaOnly: true),
            $this->journeyChina(3, title: self::LABEL_ORDER_FROM_CHINA),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function footerItemsTz(): array
    {
        return [
            $this->footerAboutGroup(0),
            $this->footerContactGroup(1),
            $this->footerQuickLinksGroup(2, includeBothJourneys: false, chinaOnly: false),
            $this->journeyTz(3, title: 'Buy From TZ'),
        ];
    }

    /** @return array<string, mixed> */
    private function footerAboutGroup(int $position): array
    {
        return [
            'title' => 'About',
            'item_type' => CmsNavigationItemType::Group,
            'visibility' => CmsNavigationVisibility::Public,
            'position' => $position,
            'children' => [
                $this->link('Our Story', '/#about', CmsNavigationVisibility::Public, 0),
                $this->link('Why Choose Us', '/#about', CmsNavigationVisibility::Public, 1),
                $this->link(self::LABEL_ORDER_FROM_CHINA, '/#order-from-china', CmsNavigationVisibility::Public, 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function footerContactGroup(int $position): array
    {
        return [
            'title' => 'Contact',
            'item_type' => CmsNavigationItemType::Group,
            'visibility' => CmsNavigationVisibility::Public,
            'position' => $position,
            'children' => [
                $this->link('hello@chinaordertz.com', 'mailto:hello@chinaordertz.com', CmsNavigationVisibility::Public, 0),
                $this->link('+255 123 456 789', 'tel:+255123456789', CmsNavigationVisibility::Public, 1),
                $this->link('Dar es Salaam, Tanzania', '/#contact', CmsNavigationVisibility::Public, 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function footerQuickLinksGroup(int $position, bool $includeBothJourneys, bool $chinaOnly = false): array
    {
        $children = [
            $this->link(self::LABEL_MY_ORDERS, '/orders', CmsNavigationVisibility::AuthOnly, 0),
        ];

        if ($includeBothJourneys || $chinaOnly) {
            $children[] = $this->link(self::LABEL_ORDER_FROM_CHINA, '/products?origin=china', CmsNavigationVisibility::Public, 1);
        }
        if ($includeBothJourneys || ! $chinaOnly) {
            $children[] = $this->link(self::LABEL_BUY_FROM_TZ, '/buy-from-tz', CmsNavigationVisibility::Public, count($children));
        }

        $children[] = $this->link('Featured Products', '/#products', CmsNavigationVisibility::Public, count($children));
        $children[] = $this->link(self::LABEL_SIGN_IN, '/login', CmsNavigationVisibility::GuestOnly, count($children));

        return [
            'title' => 'Quick Links',
            'item_type' => CmsNavigationItemType::Group,
            'visibility' => CmsNavigationVisibility::Public,
            'position' => $position,
            'children' => $children,
        ];
    }

    /** @return array<string, mixed> */
    private function journeyChina(int $position, string $title = self::LABEL_ORDER_FROM_CHINA): array
    {
        return [
            'title' => $title,
            'item_type' => CmsNavigationItemType::Journey,
            'visibility' => CmsNavigationVisibility::Public,
            'target_type' => null,
            'target_value' => CmsCommerceContext::ChinaImport->value,
            'position' => $position,
        ];
    }

    /** @return array<string, mixed> */
    private function journeyTz(int $position, string $title = self::LABEL_BUY_FROM_TZ): array
    {
        return [
            'title' => $title,
            'item_type' => CmsNavigationItemType::Journey,
            'visibility' => CmsNavigationVisibility::Public,
            'target_type' => null,
            'target_value' => CmsCommerceContext::TzLocal->value,
            'position' => $position,
        ];
    }

    /** @return array<string, mixed> */
    private function link(
        string $title,
        string $href,
        CmsNavigationVisibility $visibility,
        int $position,
    ): array {
        return [
            'title' => $title,
            'item_type' => CmsNavigationItemType::Link,
            'visibility' => $visibility,
            'target_type' => CmsCtaTargetType::Url,
            'target_value' => $href,
            'position' => $position,
        ];
    }
}
