# Retail Intelligence & Analytics (TASK 040)

Read-only analytics over existing commerce engines. Does **not** own Orders, Payments, Inventory, Profit, or Returns data.

## Architecture

```
Commerce Engines → RetailAnalyticsEngine → /admin/analytics/* → Admin UI
```

Store scope uses `ActiveStoreContext` (same rules as POS).

## APIs

| Method | Path |
|--------|------|
| GET | `/admin/analytics/dashboard` |
| GET | `/admin/analytics/sales` |
| GET | `/admin/analytics/profit` |
| GET | `/admin/analytics/payments` |
| GET | `/admin/analytics/inventory` |
| GET | `/admin/analytics/returns` |
| GET | `/admin/analytics/customers` |
| GET | `/admin/analytics/promotions` |
| GET | `/admin/analytics/stores` |
| GET | `/admin/analytics/sessions` |
| GET | `/admin/analytics/{type}/export?format=csv\|xlsx` |

### Filters

`from`, `to`, `store_id`, `cashier_id`, `customer_id`, `category_id`, `product_id`, `payment_method`, `promotion_id`, `return_reason_id`, `pos_only`

### Charts

Responses include library-agnostic `charts` / `series` payloads (`type`, `key`, `label`, `series[].points`).

## Permissions

| Role | Scope |
|------|-------|
| Store Cashier | Assigned store(s); own cashier filter forced |
| Master Cashier | Assigned stores |
| Super Admin | All stores (optional `store_id`) |

## Export audit

Exports of `profit`, `payments`, `inventory`, and `customers` emit `analytics_report_exported`.

## UI

`/admin/analytics` — executive + section dashboards with filters and export.
