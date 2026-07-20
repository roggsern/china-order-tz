# ADR 048 — CMS Hero Experience Engine

## Status

Accepted (Sprint 2)

## Context

Sprint 1 delivered homepage layouts and typed sections. Homepage `HERO` sections need ordered, schedulable, commerce-aware slides with media references and CTAs — without stuffing slide collections into section `configuration` JSON, and without duplicating Media / Product / Store / Catalog engines.

## Decision

1. **Hero slides are first-class entities** (`CmsHeroSlide` / `cms_hero_slides`) belonging to a `CmsHomepageSection` whose `section_type` is `HERO`.

2. **Slides are not stored in section JSON.** Section `configuration` remains presentation-only (autoplay, heights, etc.). Slide content lives in dedicated rows.

3. **Commerce context is inherited** from `CmsHeroSlide → section → layout → commerce_context`. It is not duplicated on the slide.

4. **Media references** use the existing `media` table via `desktop_media_id` / `mobile_media_id` (`nullOnDelete`). Image MIME only in Sprint 2. No new media library or upload API.

5. **CTA validation** is centralized in `CmsCtaTargetValidationService`:
   - `NONE`, `URL` (http/https only), `PRODUCT`, `STORE`, `CATEGORY`, `BRAND`, `PROMOTION`, `PAGE`, `CHINA_ORDER_FORM`
   - Channel-safe: no TZ stores on China layouts; no `CHINA_ORDER_FORM` on TZ layouts; GLOBAL rejects channel-specific PRODUCT/STORE/CATEGORY/BRAND targets rather than guessing

6. **Scheduling (UTC / app timezone):** storefront-visible iff `ACTIVE` + `is_visible` + parent HERO section visible + parent layout is active default + `starts_at` null|≤now + `ends_at` null|>now.

7. **Storefront payload** uses `CmsHeroSlideStorefrontResource` with resolved CTA objects `{ type, label, value, url }`. Deep-link path resolution for entity CTAs remains a frontend concern; only `URL` includes `url`.

## Consequences

- Admins can manage hero experiences per GLOBAL / CHINA_IMPORT / TZ_LOCAL layout.
- Storefront homepage response nests eligible `hero_slides` under HERO sections.
- Future video/personalization can extend Media MIME rules and eligibility without redesigning the entity.

## Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Embed slides in section JSON | No FK integrity, weak ordering/scheduling, hard to audit |
| Polymorphic `cta_target_type` + morph columns | Project prefers explicit typed columns; avoid leaking model class names |
| New CMS media table | Duplicates stub `media` table |
| Silent GLOBAL product targeting | Would mix journeys |

## Future extensions

- Video hero media when Media pipeline supports it safely
- Advertisement / campaign sections (Sprint 3+)
- CTA deep-link map shared with `apps/web`
- A/B testing and personalization (explicitly out of scope)
