# TASK 046 — Commercial Homepage & Advertising Platform

## Purpose

Turn the CHINA ORDER TZ homepage into a living commercial surface that continuously promotes products, promotions, sponsors, campaigns, stores, and seasonal events — while preserving the dual journeys:

1. **Order from China**
2. **Buy from TZ**

No changes to Orders, Payments, Inventory, CRM, Loyalty, Growth, or Authentication engines.

## Homepage architecture

Page: `apps/web/src/app/(storefront)/page.tsx`

| Order | Section | Component |
|------:|---------|-----------|
| 1 | Hero carousel | `HeroCarousel` |
| 2 | Homepage banner ads | `HomepageAdRail` |
| 3 | Flash Deals | `FlashDeals` |
| 4 | Featured Collections | `FeaturedCollections` |
| 5 | Shop by Store | `ShopByStore` (TZ storefront API) |
| 6 | Featured Products | existing `FeaturedProducts` |
| 7 | Mid-page ads | `HomepageAdRail` |
| 8 | New Arrivals (China / TZ split) | `NewArrivalsSplit` |
| 9 | Best Sellers | `BestSellers` |
| 10 | Trusted Partners | `SponsorPartners` |
| 11 | Why Choose Us | `CommercialWhyChooseUs` |
| 12 | Trust indicators | `TrustIndicators` |
| 13 | Newsletter | `CommercialNewsletter` |

Shell additions:

- Header: **Trending Searches** under desktop search
- Footer: **footer placement** ad rail

## Content layer (CMS-ready)

```
apps/web/src/lib/content/homepage/
  types.ts                 # contracts
  seed.ts                  # current commercial content
  schedule.ts              # active window + priority
  get-homepage-content.ts  # loader (swap for CMS later)
  index.ts
```

Sections consume `getHomepageContent()` — they do **not** hardcode sponsor/ad copy.

### Future CMS integration

Replace the body of `getHomepageContent()` with a public read API, keeping the same `HomepageContent` / `ResolvedHomepageContent` shapes. Admin can later manage:

- Hero slides
- Advertisements (placements, schedule, priority, desktop/mobile images)
- Sponsors
- Flash deals
- Featured collections
- Section copy

## Advertisement system

Each advertisement supports:

- Title, subtitle, description, CTA, target URL
- Image / desktop image / mobile image (optional; gradient fallback today)
- Display start / end, priority, status
- Sponsor name, advertisement type, placement

Placements:

| Placement | Usage |
|-----------|--------|
| `hero` | Reserved for hero-driven ads (slides also have their own schedule) |
| `homepage_banner` | Below hero |
| `mid_page` | Between product rails |
| `footer` | Footer commercial area |

Inactive / out-of-window items are filtered by `filterActiveScheduled()`.

## Sponsor system

`HomepageSponsor` entries power the **Trusted Partners** logo grid. Seed includes banks/cards/telcos (NMB, CRDB, NBC, Visa, Mastercard, Azam, Vodacom, Halotel, Airtel). Logos are text marks today; `logoUrl` is ready for real assets.

## Hero carousel

Slide types:

- `china` — Order from China campaign
- `tz` — Buy from TZ campaign
- `sponsor` — partner advertising
- `seasonal` — rotating seasonal campaigns

UX: autoplay (respects `prefers-reduced-motion`), pause on hover, touch swipe, keyboard arrows, dots + prev/next.

## Product rails

| Rail | Data source |
|------|-------------|
| Flash Deals | Content seed (countdown client-side) |
| Featured | Existing catalog featured API |
| New Arrivals | `getHomeNewArrivalsByOrigin('china'|'tz')` |
| Best Sellers | Heuristic: featured + rating until rank API exists |
| Shop by Store | `GET /storefront/tz/stores` |

## Product promotion labels

`ProductCardBadges` supports: New, Hot, Best Seller, Limited Offer, Discount %, Featured, Trending.

## Trust indicators

Platform-level only (Secure Checkout, Reliable Delivery, Customer Support, Return Policy, Official Store, Quality Checked). **Not** supplier verification.

## Performance

- Reserved hero min-heights to limit CLS
- Lazy store banner images via `next/image`
- Suspense boundaries around catalog/store fetches
- Content filtering is pure/local (no ad network scripts)

## Tests

```bash
cd apps/web
npm run test:homepage
```

Covers hero types/labels, ad scheduling/placements, sponsors, flash deals, section copy, carousel index helpers.

## Deferred enhancements

1. Admin CMS UI + persistence API for homepage content
2. Real sponsor logo / campaign creative assets (CDN)
3. Purchase-rank bestseller endpoint
4. Flash deals pinned to live product IDs with inventory-aware CTA
5. Personalized hero ordering
6. Visual / Playwright homepage screenshots in CI
