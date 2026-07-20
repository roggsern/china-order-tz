# Growth Platform & Customer Engagement Engine (TASK 043)

Orchestration layer for CHINA ORDER TZ. Growth owns **segments**, **campaigns**, **journeys**, and **engagement tracking**. It does **not** own customers, orders, payments, discount math, or notification providers.

## Architecture

```
Customer Data (CRM)
        ↓
Segmentation Engine
        ↓
Campaign / Journey Engine
        ↓
Notification Platform (channels)
        ↓
Customer Response → Analytics
```

| Concern | Owner |
|---------|--------|
| Identity / metrics / tags | CRM (`CustomerProfile`, `CustomerMetric`) |
| Messaging | Notification Platform |
| Discount / coupon math | Promotion Engine |
| Points / tiers | Loyalty Engine |
| Segments / campaigns / journeys | Growth Platform |

## Segmentation

Rules JSON shape:

```json
{ "all": [ { "field": "total_spend", "op": "gte", "value": 500000 } ] }
```

Supported fields include: `total_spend`, `total_orders`, `average_order_value`, `days_since_last_order`, `lifecycle_status`, `growth_stage`, `tag`, `loyalty_tier`, `category_id`, `store_id`, `marketing_opt_in`, `is_new`, `not_blocked`.

Lifecycle stages (data-driven): `new` → `active` → `vip` → `inactive` → `winback`.

## Campaigns

Types: `promotion`, `announcement`, `new_product`, `retention`, `birthday`, `winback`, `vip`.

Send path:

1. Refresh target segment members
2. Skip customers without `marketing_opt_in`
3. Deliver via `NotificationPlatform::notifyCustomer(GrowthCampaign, …)`
4. Optionally grant loyalty points via `LoyaltyEngine`
5. Optionally attach a Promotion coupon at create time

Channel preference order: WhatsApp → Email → In-app → Push → SMS.

## Journeys

Triggers: `registration`, `inactive_days`, `vip_threshold`, `birthday`, `manual`.  
`POST /admin/growth/journeys/run` enrolls matching customers and optionally sends linked campaigns.

## Admin APIs

- `GET /admin/growth/dashboard`
- `GET|POST /admin/growth/segments`
- `PUT /admin/growth/segments/{segment}`
- `POST /admin/growth/segments/{segment}/refresh`
- `GET|POST /admin/growth/campaigns`
- `GET|PUT /admin/growth/campaigns/{campaign}`
- `POST /admin/growth/campaigns/{campaign}/send`
- `GET /admin/growth/campaigns/{campaign}/analytics`
- `GET|POST /admin/growth/journeys`
- `POST /admin/growth/journeys/run`
- `GET /admin/analytics/growth`

## Customer APIs

- `GET /growth/offers`
- `GET /growth/history`

## UI

- Admin: `/admin/growth`
- Customer: `/account/growth`
- Analytics section: Growth

## Seed

`GrowthSeeder`: sample segments (VIP, inactive, new, one-time, frequent), draft campaigns, journeys, and `growth_campaign.{channel}` notification templates.

## Audit

Activity events: `growth_segment_created`, `growth_campaign_created`, `growth_campaign_updated`, `growth_campaign_sent`, `growth_journey_created`.
