# TASK 049 — CMS Featured Content Engine

See [ADR 049](./ADR_049_CMS_FEATURED_CONTENT_ENGINE.md).

## Entity

`CmsFeaturedContent` → belongs to `CmsHomepageSection` (types with `supportsFeaturedContent()`).

Not allowed on `HERO`.

## Admin API

`/api/v1/admin/cms/homepage-layouts/{layout}/sections/{section}/featured-contents`

| Method | Path |
|--------|------|
| GET/POST | `/` |
| PUT | `/reorder` (`featured_content_ids`) |
| GET/PUT/DELETE | `/{featuredContent}` |
| POST | `/{featuredContent}/visibility` |

## Storefront

Eligible featured blocks under supporting sections include:

```json
{
  "title": "...",
  "source_type": "MANUAL",
  "display_style": "GRID",
  "items": [
    { "item_type": "PRODUCT", "id": "...", "data": { /* ProductResource */ } }
  ]
}
```

## Manual configuration

```json
{
  "item_type": "PRODUCT|STORE|BRAND|CATEGORY",
  "item_ids": ["uuid", "..."]
}
```
