# ADR 050 — CMS Campaign Experience Engine

## Status

Accepted (Sprint 4)

## Context

Seasonal and promotional storefront experiences (Black Friday, Ramadan, launch weeks) need coordinated homepage layouts, heroes, featured rails, and promotion references on a schedule.

The platform already has **GrowthCampaign** for engagement messaging (segments, WhatsApp, delivery metrics). Reusing it for storefront layout orchestration would conflate marketing automation with experience configuration (ADR 047).

## Decision

1. **`CmsCampaign` is a CMS orchestration layer**, not a GrowthCampaign replacement and not a Promotion engine.

2. A campaign **references**:
   - one `CmsHomepageLayout` (same commerce context)
   - optional curated `CmsHeroSlide` set (must belong to that layout)
   - optional curated `CmsFeaturedContent` set (must belong to that layout)
   - optional `Promotion` IDs (reference only)

3. **Storefront resolution order** (exact commerce context; never China↔TZ mix):
   1. Highest-priority **ACTIVE** campaign whose schedule contains `now` and has a layout
   2. That layout’s sections → heroes / featured (filtered to campaign attachments when present)
   3. Else Sprint 1 default homepage layout (optional GLOBAL layout fallback)

4. **Default campaign** uniqueness per context uses `default_slot` (same pattern as layouts). Default campaigns cannot be archived until unset.

5. Hero and Featured **services are not modified**; curation is applied in homepage storefront hydration.

## Consequences

- Merchandisers can schedule experience swaps without code deploys.
- Growth Platform remains the engagement engine.
- Empty hero/featured attach lists mean “use all eligible children of the layout”.

## Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Extend GrowthCampaign | Wrong domain (messaging ≠ homepage experience) |
| Duplicate Promotion tables | Promotions stay in Promotion Engine |
| Embed layout snapshots | Would duplicate Sprint 1–3 entities |
| Modify Hero/Featured engines | Unnecessary; filter at resolve time |

## Future extensions

- Recurring schedules
- Personalization hooks
- Campaign analytics dashboards
- Landing-page campaigns beyond homepage
