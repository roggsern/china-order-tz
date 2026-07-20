# TASK 045 — Storefront Experience & Brand Identity

## Purpose

Premium, consistent storefront presentation for the two commerce journeys without merging catalogs or duplicating backend engines.

| Journey | Scope |
|---------|--------|
| **Order from China** | Catalog Bible categories, `CHINA_IMPORT` products, `store_id` null |
| **Buy from TZ** | Tanzanian stores, store categories, `TZ_LOCAL` products |

## Navigation visibility policy

Implemented in `apps/web/src/lib/storefront/navigation-policy.ts`.

### Guest (visitor)

Visible:

- Order from China
- Buy from TZ
- About Us
- Contact Us
- Sign In / Create Account
- Search
- Cart (guest carts remain supported)

**Not** in the DOM as primary nav:

- My Orders
- Track Order
- Notifications
- My Account / loyalty / saved addresses shortcuts

### Authenticated customer

Visible:

- Order from China
- Buy from TZ
- **My Orders** → `/orders` (existing customer order experience)
- About Us / Contact Us
- Search
- Notifications (existing bell)
- My Account
- Cart

Optional **My Orders (N)** badge uses `GET /api/v1/dashboard` → `summary.active_orders + in_transit_orders` via BFF `/api/dashboard`. Guests never call this endpoint.

### Staff / internal roles

Admin, cashier, and store-manager shells are unchanged. Staff may still preview the customer storefront; operational navigation remains in admin/POS layouts.

## Why My Orders is hidden from visitors

Hiding is UX only. Order list/detail APIs remain authenticated. Guests who open `/orders` see the existing sign-in invitation (`AuthInvitationCard`) and must authenticate. Authorization is never inferred from nav visibility alone.

## Why Track Order is nested

Primary nav uses **My Orders**. Shipment tracking lives inside order detail / track routes and only surfaces on order cards when the order status indicates a shipment (`processing`, `packed`, `shipped`, `in_transit`).

## Typography conventions

- Shared family: Geist (`--font-geist-sans` / `--sf-font`)
- Primary nav: 13px / medium (`storefrontTypography.navPrimary`)
- Exact labels: `Order from China`, `Buy from TZ`, `My Orders`, `About Us`, `Contact Us`
- “from” lowercase; “TZ” uppercase
- Tokens live in `apps/web/src/lib/storefront/typography.ts` + CSS variables in `globals.css`

## Flag assets

Inline SVG components in `apps/web/src/components/storefront/CountryFlag.tsx`:

- China (`CN`) and Tanzania (`TZ`)
- ~18–22px height, natural 3:2 ratio
- Decorative beside visible text labels (no duplicated screen-reader text)
- **Not** OS emoji flags

## Mega-menu data sources

Unchanged APIs (do not mix scopes):

- China: `/api/v1/storefront/china/*` (Catalog Bible only)
- TZ: `/api/v1/storefront/tz/stores*` (published storefront stores only)

See also:

- `ORDER_FROM_CHINA_STOREFRONT.md`
- `BUY_FROM_TZ_STOREFRONT.md`

## Active navigation route mapping

`resolveActiveJourney(pathname, search)`:

| Route pattern | Active item |
|---------------|-------------|
| `/products?origin=china`, `/categories/*`, `/brand/*` | Order from China |
| `/buy-from-tz/*`, `origin=tz\|local` | Buy from TZ |
| `/orders/*`, `/track*`, `/track-order*` | My Orders |

Visual treatment: subtle gold underline + text color (not loud backgrounds).

## Mobile navigation

Same visibility policy as desktop. Drawer:

- Locks body scroll while open
- Escape + overlay close
- Focus restored to the control that opened the menu
- ~44px tap targets
- Journey menus use accordion drill-down (existing mega-menu mobile mode)

## Homepage

`SplitCommerceHero` replaces the single-journey dark hero as the primary first viewport: balanced China / TZ panels, stacked on small screens, `prefers-reduced-motion` respected.

## Search source labels

Visual grouping only (no search backend rewrite):

- China results: `Order from China`
- Local results: `Sold by {brand}` or `Buy from TZ`

## Analytics

No storefront `trackEvent` helper exists yet. Journey analytics events are intentionally deferred until the shared analytics client is available. Do not invent ad-hoc payloads with PII.

## Deferred enhancements

1. Broader active-order badge statuses (Pending Payment, Arrived in Tanzania, Ready for Pickup) when dashboard summary exposes them without list queries.
2. Storefront analytics event wiring (`header_journey_selected`, menu open, CTA clicks).
3. Full RTL/component visual regression suite (web currently uses Node unit tests for policy + existing API storefront tests).

## Tests

- Web: `npm run test:storefront` → `navigation-policy.test.ts`
- API (existing, keep green): `OrderFromChinaStorefrontTest`, `BuyFromTzStorefrontTest`, `CustomerOrdersTest`, `CustomerOrderDetailsTest`, `CustomerDashboardTest`
