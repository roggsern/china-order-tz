# TASK 050 — CMS Campaign Experience Engine

See [ADR 050](./ADR_050_CMS_CAMPAIGN_EXPERIENCE_ENGINE.md). Distinct from Growth Platform campaigns.

## Entity

`CmsCampaign` — schedule + priority + layout FK + attach pivots.

## Admin API

`/api/v1/admin/cms/campaigns`

| Method | Path |
|--------|------|
| GET/POST | `/` |
| GET/PUT | `/{campaign}` |
| POST | `/{campaign}/activate` |
| POST | `/{campaign}/archive` |
| PATCH | `/{campaign}/priority` |
| POST | `/{campaign}/layout` |
| PUT | `/{campaign}/hero-slides` |
| PUT | `/{campaign}/featured-contents` |
| PUT | `/{campaign}/promotions` |

## Storefront

`GET /storefront/homepage` meta includes:

```json
"campaign": { "id", "name", "slug", "priority", "promotion_ids" }
```

or `null` when falling back to the default layout.
