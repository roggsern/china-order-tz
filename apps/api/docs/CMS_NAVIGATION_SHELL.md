# CMS Navigation Shell Engine

Sprint 5A — orchestration layer for primary / footer / mobile / utility navigation.  
Sprint 5B — storefront (`apps/web`) consumes this API with `navigation-policy` fallback.  
Sprint 5C-1 — default shells seeded to mirror that fallback.

## Principle

**CMS owns the shell. Commerce owns the taxonomy.**

| Concern | Owner |
|---------|--------|
| Item order, labels, visibility, enable/disable | `CmsNavigationShell` / `CmsNavigationItem` |
| Order From China mega categories | `ChinaStorefrontCatalog` + Catalog Bible |
| Buy From TZ mega stores | `TzStorefrontCatalog` / Store Engine |
| Link CTA safety + context | `CmsCtaTargetValidationService` |
| Seasonal shell selection | `CmsCampaign` ↔ shell pivot |
| Default chrome (5C-1) | `CmsDefaultNavigationShellSeeder` |

## Why defaults mirror `navigation-policy`

The web storefront already shipped with a static policy (`apps/web` `navigation-policy.ts` + `home-data` footer). Sprint 5B made CMS primary **only when a shell exists**. Empty CMS would silently keep the fallback — safe, but CMS stayed inactive.

`CmsDefaultNavigationShellSeeder` copies that exact chrome into CMS:

- Same labels (`Order from China`, `Buy from TZ`, …) — no invented copy
- Same order (journeys → My Orders → About → Contact)
- Same visibility (`PUBLIC` / `AUTH_ONLY` / `GUEST_ONLY`)
- Journeys are `item_type=JOURNEY` with `CHINA_IMPORT` / `TZ_LOCAL` — **never** category or store row copies
- Footer “Buy From TZ” is a **TZ journey** so the Store Engine hydrates live stores at resolve time

Activating CMS therefore does **not** change customer UX; it only moves the source of truth.

## How CMS becomes the primary source

```
GET /api/v1/storefront/navigation?commerce_context=GLOBAL
  → active campaign shell (if any)
  → else default seeded shell (is_default + active)
  → hydrate JOURNEY / MEGA_MENU via commerce engines
```

Web (`useStorefrontNavigation`) prefers this payload. After seeding, GLOBAL defaults resolve and CMS is live.

## Why the fallback still exists

`navigation-policy.ts` remains the offline / empty-shell / API-error safety net:

- Local/dev without seed or API
- Misconfigured commerce_context with no shell
- Transient API failures must not blank the header

Fallback is not a second product — it is a circuit breaker until CMS responds.

## Default shells (5C-1)

| Context | Types |
|---------|--------|
| `GLOBAL` | PRIMARY, FOOTER, MOBILE, UTILITY |
| `CHINA_IMPORT` | PRIMARY, FOOTER, MOBILE (China journey only) |
| `TZ_LOCAL` | PRIMARY, FOOTER, MOBILE (TZ journey only) |

All marked `active`, `is_default`, unique `default_slot` = `{CONTEXT}:{TYPE}`.

Seed: `php artisan db:seed --class=CmsDefaultNavigationShellSeeder` (also called from `DatabaseSeeder`). Idempotent.

## Admin APIs

Prefix: `/api/v1/admin/cms`

- CRUD `navigation-shells`
- `POST …/publish`, `…/archive`, `…/default`
- Nested `…/items` CRUD, reorder, enable, disable
- `PUT /cms/campaigns/{id}/navigation-shells`

## Storefront

`GET /api/v1/storefront/navigation`

Query:

- `commerce_context` (required)
- `navigation_type` (optional — omit to resolve all types)
- `audience` = `guest` | `authenticated` | `admin_preview`
- `hydrate_mega_menus` = bool (default true)

## Resolution

```
Active campaign shell (matching type + context)
  → else default shell
  → hydrate enabled items
  → visibility filter
  → JOURNEY / MEGA_MENU via China or TZ engines
```

Never mixes `CHINA_IMPORT` and `TZ_LOCAL`.
