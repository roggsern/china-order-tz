# ADR 049 — CMS Featured Content Engine

## Status

Accepted (Sprint 3)

## Context

Homepage (and future landing/campaign/category surfaces) need dynamic rails of products, stores, brands, and categories. Embedding product payloads inside `CmsHomepageSection.configuration` would duplicate commerce data and couple CMS to catalog schema changes.

Sprint 1 sections are containers. Sprint 2 proved first-class child entities for Hero. Featured rails need the same pattern with **source strategies** that query existing engines.

## Decision

1. **`CmsFeaturedContent` is a reusable engine**, owned by a section type that `supportsFeaturedContent()` — not by HERO (Hero uses `CmsHeroSlide`).

2. **CMS stores references and query recipes only** (`source_type` + `configuration` JSON). Product name/price/images/stock always come from Product / Store / Brand / Category / Promotion engines at resolve time.

3. **Source types** describe *how* items are generated: `MANUAL`, `BEST_SELLERS`, `NEW_ARRIVALS`, `MOST_VIEWED`, `PROMOTION`, `CATEGORY`, `BRAND`, `STORE`, `COLLECTION`, `SEARCH_FILTER`.

4. **Commerce context is inherited** from the parent layout. Validation rejects incompatible references (e.g. TZ stores under CHINA_IMPORT; ranked product sources under GLOBAL).

5. **Storefront** nests eligible `featured_contents` under supporting sections and includes **resolved `items`** `{ item_type, id, data }` so the frontend never guesses the source.

6. **COLLECTION** currently means a curated list of category IDs until a Collection engine exists. **MOST_VIEWED** uses a featured+recent heuristic until analytics exist. **BEST_SELLERS** prefers `order_items` quantity sums, then falls back to featured ranking.

## Consequences

- Same engine can power homepage, landing pages, campaign pages, and future mobile/personalization surfaces.
- Catalog schema changes do not require CMS migrations for display fields.
- Admins must pick a channel layout for product-driven rails.

## Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Embed product snapshots in section JSON | Duplicates Product Engine; stale prices/images |
| Homepage-only “FeaturedProducts” table | Not reusable for landing/campaign/mobile |
| Polymorphic morph targets for every item | Leaks model class names; harder public API |
| Modify Hero Engine for product rails | Wrong abstraction; Hero is media+CTA slides |

## Future extensions

- Real Collection entity
- View-count / analytics-backed MOST_VIEWED
- Personalized featured rails
- Attach featured content to non-homepage page entities
