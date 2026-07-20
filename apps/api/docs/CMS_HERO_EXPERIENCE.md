# TASK 048 — CMS Hero Experience Engine

See [ADR 048](./ADR_048_CMS_HERO_EXPERIENCE_ENGINE.md). Builds on [TASK 047](./CMS_HOMEPAGE_FOUNDATION.md).

## Entity

`CmsHeroSlide` → belongs to `CmsHomepageSection` (`HERO` only).

Commerce context inherited from layout. Status: `draft|active|archived` (`CmsStatus`).

## Admin APIs

Base: `/api/v1/admin/cms/homepage-layouts/{layout}/sections/{section}/hero-slides`

| Method | Path | Notes |
|--------|------|--------|
| GET | `/` | List slides |
| POST | `/` | Create |
| PUT | `/reorder` | Complete unique `slide_ids` set |
| GET | `/{heroSlide}` | Show |
| PUT | `/{heroSlide}` | Update |
| POST | `/{heroSlide}/activate` | status → active |
| POST | `/{heroSlide}/archive` | status → archived |
| POST | `/{heroSlide}/visibility` | Toggle `is_visible` |
| DELETE | `/{heroSlide}` | Hard delete |

Nested binding enforces layout ↔ section ↔ slide ownership (404 on mismatch).

## Storefront

`GET /api/v1/storefront/homepage` — HERO sections include `hero_slides` (eligible only) via `CmsHeroSlideStorefrontResource`.

CTA shape:

```json
{ "type": "PRODUCT", "label": "Shop Now", "value": "<uuid>", "url": null }
```

`URL` type sets `url` to the validated http(s) value. Entity deep links are resolved by the frontend from `type` + `value`.

## CTA context rules (summary)

| Type | GLOBAL | CHINA_IMPORT | TZ_LOCAL |
|------|--------|--------------|----------|
| URL / PAGE / PROMOTION | ✓ | ✓ | ✓ |
| PRODUCT / CATEGORY / BRAND | ✗ | ✓ (channel match) | ✓ (channel match) |
| STORE | ✗ | ✗ | ✓ |
| CHINA_ORDER_FORM | ✓ | ✓ | ✗ |

## Audit

`cms_hero_slide_*` events on `activity_logs` via `CmsPlatformAudit`.
