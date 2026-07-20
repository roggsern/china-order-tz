# ADR 047 — Experience Platform CMS Core and Commerce Separation

## Status

Accepted (Sprint 1)

## Context

CHINA ORDER TZ runs two strictly separated commerce journeys:

1. **Order from China** (`CHINA_IMPORT`)
2. **Buy from TZ** (`TZ_LOCAL`)

The platform already owns Products, Stores, Promotions, Media, Pages, Settings, Growth, and Audit engines. Homepage commercial content must become CMS-managed without duplicating those engines or mixing journeys.

Frontend TASK 046 introduced a CMS-ready content contract under `apps/web/src/lib/content/homepage/`. Sprint 1 delivers the API foundation for homepage layouts and sections.

## Decision

1. **CMS is an orchestration layer.** It stores layout/section configuration and references. It does not copy product, store, promotion, media, page, customer, order, or inventory data.

2. **Existing commerce engines remain sources of truth.** Section `configuration` JSON may hold IDs/filters that later resolvers interpret against Catalog, Stores, Promotions, etc.

3. **Strict China Import / TZ Local separation.** Layouts are scoped by `CmsCommerceContext`:
   - `GLOBAL` — shared shell content
   - `CHINA_IMPORT` — Order from China only
   - `TZ_LOCAL` — Buy from TZ only  
   Channel values reuse `CommerceChannelCode` string values. `GLOBAL` is CMS-only and is **not** added to `CommerceChannelCode`.

4. **Cms-prefixed entities** avoid collisions with stub `Page` / `Media` / `Setting` and with `GrowthCampaign`:
   - `CmsHomepageLayout`
   - `CmsHomepageSection`

5. **Module packaging follows Growth/Promotions:** UUID PKs, `created_by` → admins, status lifecycle (`draft|active|archived`), ActivityLog audits via `CmsPlatformAudit`, admin routes under `/api/v1/admin/cms/...`, public read under `/api/v1/storefront/homepage`.

6. **Default uniqueness** uses nullable unique `default_slot` (= commerce context when default) plus transactional `lockForUpdate` clears.

7. **Storefront GLOBAL fallback** applies only when the requested channel has no active default layout. It never returns the opposite channel’s layout. Clients may disable fallback with `allow_global_fallback=0`.

## Consequences

- Admin can manage homepage structure without shipping code for section order/visibility.
- Storefront can load visible sections for a context from the API.
- Hero Manager, Campaign Manager, Advertisement Manager, Navigation, Footer, and SEO remain future sprints.
- Configuration validation is structural only in Sprint 1 (array + optional commerce-context compatibility).

## Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Extend stub `pages` / `settings` tables | Wrong grain; homepage needs ordered typed sections |
| Reuse `GrowthCampaign` for homepage ads | Growth owns engagement campaigns, not storefront layout |
| Add `GLOBAL` to `CommerceChannelCode` | Pollutes commerce channel model; GLOBAL is not a sales channel |
| Soft-delete layouts | Newer engines prefer status archival; matches Growth/Promotions |
| Silent merge of China + TZ content | Violates dual-journey separation |
| New `cms_audit_logs` table | Platform already has append-only `activity_logs` |

## Future extensions (Sprint 2+)

- Hero slide manager
- Advertisement / sponsor entities referenced by section configuration
- Featured collection / product rail resolvers against Catalog & Stores
- Navigation & footer managers
- Wire `apps/web` `getHomepageContent()` to `GET /storefront/homepage`
