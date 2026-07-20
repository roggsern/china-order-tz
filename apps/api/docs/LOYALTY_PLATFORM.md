# Loyalty & Customer Rewards Platform (TASK 041)

Independent rewards layer for CHINA ORDER TZ. Loyalty does **not** replace CRM, Promotion, Pricing, or Order engines.

## Architecture

```
Customer Activity → Loyalty Engine → Points / Tier / Rewards → Customer Benefits
```

| Concern | Owner |
|---------|--------|
| Customer identity | CRM (`CustomerProfile`) |
| Discount calculation | Promotion Engine |
| Points / tiers / rewards | Loyalty Engine |
| Orders & payment | Order / Payment engines |

## Earn trigger

Points are awarded only after `PaymentConfirmed` (successful payment). Cart, quote, unpaid, and cancelled orders do not earn points.

Listener: `HandleLoyaltyLifecycle::onPaymentConfirmed`  
Service: `LoyaltyEngine::earnForPaidOrder`

Walk-in POS sales (`user_id` null) never create a loyalty account.

## Ledger

`loyalty_ledger_entries` is append-only (`updated_at` disabled). Balance changes always go through `LoyaltyEngine::postLedger` with types: `earn`, `redeem`, `expire`, `adjust`.

## Redemption

Redeeming a discount reward creates a one-time Promotion coupon. POS/storefront apply it via existing `promotion_code` / DiscountResolver — no parallel discount math.

## Admin APIs

- `GET /admin/loyalty/dashboard`
- `GET /admin/loyalty/customers`
- CRUD: `/admin/loyalty/tiers`, `/admin/loyalty/rules`, `/admin/loyalty/rewards`
- `POST /admin/loyalty/customers/{account}/adjust` (reason required)
- `GET /admin/analytics/loyalty`

## Customer APIs

- `GET /loyalty/profile`
- `GET /loyalty/transactions`
- `GET /loyalty/rewards`
- `POST /loyalty/redeem`

## POS

- `GET /pos/loyalty/lookup`
- `POST /pos/loyalty/{account}/redeem`

## UI

- Admin: `/admin/loyalty`
- Customer: `/account/loyalty`
- POS: loyalty panel inside cashier customer section

## Seed defaults

`LoyaltySeeder`: Bronze → Platinum tiers, spend rule `1000 TZS = 10 points` (12-month expiry config), sample 5% voucher reward.
