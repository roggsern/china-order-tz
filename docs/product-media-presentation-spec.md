# Product media presentation specification

Canonical guidance for CHINA ORDER TZ product photography across Admin uploads, Web, and Mobile.

This document extends [`PRODUCT_MEDIA_UPLOAD_CONTRACT.md`](./PRODUCT_MEDIA_UPLOAD_CONTRACT.md) with **presentation** requirements. Upload MIME/size limits in that contract remain authoritative.

## Why this spec exists

Storefront surfaces disagree today:

| Surface | Container | Fit | Effect |
|---------|-----------|-----|--------|
| Web / Mobile PLP | **1:1** | `cover` | crops tall/wide shots |
| Web PDP (desktop) | 1:1 → 4:5 → 1:1 by breakpoint | `cover` | crops |
| Mobile PDP (pre–Wave 5B) | full width × 320px | `contain` | letterbox gaps |

Uploads accept **any aspect** (max 5000×5000 only). Root cause is therefore a **combination** of unconstrained source ratios, mismatched containers, and mixed fit policies.

## Official master image recommendation

| Rule | Value | Rationale |
|------|-------|-----------|
| **Aspect ratio** | **1:1 (square)** | Matches PLP cards on Web + Mobile — highest-frequency surface |
| **Preferred master size** | **1600 × 1600 px** | Sharp on retina PLP/PDP; under 5000² upload cap |
| **Minimum size** | **1000 × 1000 px** | Avoid soft cards on dense grids |
| **Safe zone** | Keep the product inside the center **~90%** | Outer ~5% per edge may crop under `cover` on PLP |
| **Background** | Clean solid / soft studio (white, light gray, or brand-neutral cream) | Minimizes letterbox contrast when PDP uses `contain` |
| **Subject framing** | Full product visible; no edge-clipped limbs/hardware | Readable under both cover (PLP) and contain (PDP) |
| **Formats** | WebP preferred; JPEG/PNG accepted | Per upload contract |
| **Max file size** | **10 MB** | Per upload contract |
| **Color profile** | sRGB | Consistent storefront rendering |

### Variant / configuration images

- Same **1:1** master rules as product gallery images.
- Show the **exact sellable look** (color/finish) for that configuration.
- Prefer identical framing across a color family so gallery swaps feel intentional.

### What not to upload

- Extreme panoramic or ultra-tall lifestyle shots as the **primary** catalog image (use secondary lifestyle frames only if the merchant accepts PLP crop).
- Text baked into the image (prices, watermarks, “sale” badges).
- HEIC/HEIF (rejected by contract).

## Storefront rendering contract (Wave 5B+)

| Surface | Container | Fit | Notes |
|---------|-----------|-----|-------|
| PLP / search / home cards | **1:1** | `cover` | Expect mild crop; safe zone protects subject |
| Mobile PDP gallery | **1:1** frame | `contain` | Full product visible; premium muted frame absorbs gaps |
| Web PDP | Keep existing responsive stage; prefer masters that survive 1:1 cover | `cover` | Align future web polish to this spec |
| Videos | Same gallery frame | Poster `cover`; embed fill | YouTube / Vimeo only |

## Videos (existing contract)

- Admin saves **YouTube or Vimeo HTTPS URLs** (not arbitrary HTML, not direct MP4).
- Customer detail API exposes `videos[]`: `{ id, url, thumbnail_url, title, alt_text, sort_order }`.
- Storefronts must validate/normalize embed URLs client-side and never render admin-supplied HTML.

## Admin / ops checklist

1. Export or shoot square masters at ≥1000² (prefer 1600²).
2. Leave breathing room around the product (safe zone).
3. Prefer WebP under 10 MB.
4. Attach variant images with the same framing language.
5. Add YouTube/Vimeo walkthroughs via Product Media → video URL (optional).
