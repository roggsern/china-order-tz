# TASK 047 — Experience Platform CMS Core (Homepage Foundation)

## Purpose

Admin-managed homepage layouts and sections for CHINA ORDER TZ, without mixing Order from China and Buy from TZ, and without replacing Catalog / Stores / Promotions / Media engines.

See also: [ADR 047](./ADR_047_CMS_CORE_COMMERCE_SEPARATION.md), [TASK 046](./COMMERCIAL_HOMEPAGE.md).

## Entities

| Model | Table | Notes |
|-------|-------|-------|
| `CmsHomepageLayout` | `cms_homepage_layouts` | `commerce_context`, `status`, `is_default`, unique `slug`, unique `default_slot` |
| `CmsHomepageSection` | `cms_homepage_sections` | Belongs to layout; typed section; `position`; `configuration` JSON |

Statuses: `draft` \| `active` \| `archived`  
Contexts: `GLOBAL` \| `CHINA_IMPORT` \| `TZ_LOCAL`

## Admin APIs (`/api/v1/admin/cms/...`)

Requires Sanctum admin + CMS policy (`super_admin`, `administrator`, or `manager`).

| Method | Path | Action |
|--------|------|--------|
| GET | `/homepage-layouts` | List |
| POST | `/homepage-layouts` | Create |
| GET | `/homepage-layouts/{layout}` | Show (+ sections) |
| PUT | `/homepage-layouts/{layout}` | Update |
| POST | `/homepage-layouts/{layout}/default` | Set default (unsets prior for same context) |
| POST | `/homepage-layouts/{layout}/archive` | Archive (rejects if default) |
| GET | `/homepage-layouts/{layout}/sections` | List sections |
| POST | `/homepage-layouts/{layout}/sections` | Create section |
| PUT | `/homepage-layouts/{layout}/sections/reorder` | Reorder (`section_ids` = complete unique set) |
| PUT | `/homepage-layouts/{layout}/sections/{section}` | Update section |
| POST | `/homepage-layouts/{layout}/sections/{section}/visibility` | Toggle visibility |
| DELETE | `/homepage-layouts/{layout}/sections/{section}` | Delete section |

## Storefront API

`GET /api/v1/storefront/homepage?commerce_context=CHINA_IMPORT|TZ_LOCAL|GLOBAL&allow_global_fallback=1`

- Returns active **default** layout only
- Visible sections only, ordered by `position`
- GLOBAL fallback only when channel default missing (never cross-channel)

## Audit

Events recorded on `activity_logs` via `CmsPlatformAudit` (e.g. `cms_homepage_layout_created`, `cms_homepage_sections_reordered`).

## Out of scope (later sprints)

Hero Manager, Campaign Manager, Advertisement Manager, Featured Items resolvers, Navigation, Footer shell, SEO, Media Library changes.
