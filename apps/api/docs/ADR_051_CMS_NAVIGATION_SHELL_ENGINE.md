# ADR 051 — CMS Navigation Shell Engine

## Status

Accepted (Sprint 5A)

## Context

Storefront chrome (primary, footer, mobile, utility navigation) must be configurable without deploys, while **Order From China** category trees and **Buy From TZ** store trees already have authoritative engines:

- `ChinaStorefrontCatalog` (+ Catalog Bible allowlist)
- `TzStorefrontCatalog` / Store Engine

Duplicating those trees inside CMS would create a second source of truth and risk China↔TZ mixing.

## Decision

1. **`CmsNavigationShell` + `CmsNavigationItem` orchestrate navigation chrome** (labels, order, visibility, link CTAs, journey/mega slots). They do **not** own commerce taxonomy.

2. **Item types**
   - `LINK` — validated via existing `CmsCtaTargetValidationService`
   - `JOURNEY` — `target_value` ∈ `{CHINA_IMPORT, TZ_LOCAL}` only; resolver returns engine metadata
   - `MEGA_MENU` — same journey values; resolver **calls** China/TZ storefront catalogs at resolve time (references, no copies)
   - `GROUP` — structural parent only

3. **Default uniqueness** is per `(commerce_context, navigation_type)` via `default_slot` (`CHINA_IMPORT:PRIMARY`, …).

4. **Storefront resolution order**
   1. Active scheduled `CmsCampaign` with an attached active shell of the requested type (same commerce context)
   2. Else default active shell for context + type
   3. Hydrate enabled items → filter visibility → resolve JOURNEY / MEGA_MENU via commerce engines

5. Campaigns attach shells by reference (`cms_campaign_navigation_shell`). One shell per `navigation_type` per campaign. Context must match.

6. **Out of scope for this ADR:** web Header/Footer wiring (Sprint 5B), curated pin/hide of individual categories/stores, recurring schedules.

## Consequences

- Merchandisers can swap nav shells seasonally via campaigns without touching Catalog Bible or Store Engine.
- Mega-menu live data continues to come from existing storefront APIs/engines.
- Empty storefront response (`shell: null`) when no default/campaign shell exists — web keeps its static policy fallback until Sprint 5B.

## Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| CMS-owned category/store trees | Duplicates Catalog Bible / Store Engine |
| Extend GrowthCampaign | Wrong domain (messaging ≠ chrome) |
| Embed mega-menu snapshots on shells | Stale data; breaks Bible/store truth |
| Modify ChinaStorefrontCatalog / Store Engine | Unnecessary; call at resolve time |

## Related

- ADR 047 — CMS / core commerce separation
- ADR 050 — CmsCampaign homepage orchestration
- Navigation Architecture Audit (Sprint 5 Phase 1)
- Sprint 5C-1 — `CmsDefaultNavigationShellSeeder` mirrors web `navigation-policy` so CMS activates without UX drift; policy remains API-failure fallback
