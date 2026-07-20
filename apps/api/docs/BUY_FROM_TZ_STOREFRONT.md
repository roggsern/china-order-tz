# BUY FROM TZ Storefront Foundation (TASK 044)

BUY FROM TZ is a **local marketplace entry point** over the existing `stores` table — not brands, suppliers, or China catalog vendors.

## Architecture

```
BUY FROM TZ
  → Active storefront stores (ZION / PEACHY / TZUR / ROVI)
  → Store-scoped categories
  → Store-scoped TZ_LOCAL products
```

| Concern | Source |
|---------|--------|
| Store list | `stores` (`storefrontVisible` scope) |
| Categories | `categories.store_id` + `origin=tz` |
| Products | `products.store_id` + commerce channel `TZ_LOCAL` |
| China import | Separate — never mixed into `/storefront/tz/*` |

## Storefront flags

On `stores`:

- `storefront_enabled`
- `storefront_visible`
- `storefront_featured`
- `storefront_sort_order`

Admin can manage these via existing store update APIs.

## APIs

- `GET /storefront/tz/stores`
- `GET /storefront/tz/stores/{store}`
- `GET /storefront/tz/stores/{store}/categories`
- `GET /storefront/tz/stores/{store}/products`
- `GET /storefront/tz/stores/{store}/products/{product}`

Legacy `GET /stores` is also storefront-scoped (no brands).

## Web

- Mega menu: `BuyFromTzMegaMenu` (viewport-limited, stores only)
- Routes: `/buy-from-tz`, `/buy-from-tz/{store}`, `/buy-from-tz/{store}/category/{category}`, `/buy-from-tz/{store}/product/{product}`
- BFF: `/api/storefront/tz/...`

## Seed

`StoreSeeder` + `TzStoreCategorySeeder` define the four retail units and their category trees.
