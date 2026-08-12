# CHINA ORDER TZ — Mobile API Contract v1

**Status:** Official mobile client contract (documentation of **current** Laravel API behavior)  
**Base URL prefix:** `/api/v1`  
**Source of truth:** `apps/api/routes/api.php`, customer controllers, and Contract v1 helpers (`App\Support\Http\ApiResponse`, `bootstrap/app.php` exception rendering)  
**Last aligned with codebase:** 2026-08-10  

This document describes what is **implemented today**. It does not invent endpoints. Items not available for mobile yet are marked **Future**.

---

# 1. Overview

## Platform

**CHINA ORDER TZ** is a dual-journey commerce platform:

| Journey | Commerce channel code | Customer label (storefront) | Typical catalog |
|---------|----------------------|-----------------------------|-----------------|
| Order from China | `CHINA_IMPORT` | ORDER FROM CHINA | China import catalog / categories / brands |
| Buy from TZ | `TZ_LOCAL` | Buy from TZ | Local stores, store categories, store products |

The **mobile app** is a customer client of the same Laravel API used by the web storefront (`apps/web`). Admin/POS APIs are out of scope for this contract.

## Contract philosophy

- **Additive changes only** for Contract v1 (`success`, `code`, `request_id`, etc.).
- Existing resource keys, status strings, and pricing fields remain for **backward compatibility** with web.
- Prefer `ApiResponse` envelopes on migrated customer surfaces (Auth, Cart, Checkout, Orders, Payments). Some public catalog/CMS/search endpoints still return `{ success, data }` (or Resource collections with `success`) without the full helper; treat those as compatible success shapes.
- Do **not** mix `CHINA_IMPORT` and `TZ_LOCAL` lines in one cart/checkout (server-enforced).

## Authentication header

Authenticated routes use Laravel Sanctum:

```http
Authorization: Bearer {token}
Accept: application/json
```

Customer routes that require auth also use middleware: `auth:sanctum`, `ensure.user`, `user.active`.

## Success envelope (Contract v1)

```json
{
  "success": true,
  "message": "Optional human message",
  "data": {},
  "meta": {},
  "request_id": "uuid"
}
```

Extras (e.g. auth `token`) may appear as **top-level** keys alongside `success` / `data`.

## Error envelope (Contract v1)

```json
{
  "success": false,
  "code": "validation_failed",
  "message": "Human-readable summary",
  "errors": {
    "field": ["…"]
  },
  "request_id": "uuid"
}
```

---

# 2. Authentication

**Controller:** `App\Http\Controllers\AuthController`

## 2.1 Register

| | |
|--|--|
| **Method** | `POST` |
| **Route** | `/api/v1/register` |
| **Auth** | Public (throttled: `customer-register`) |

**Request example**

```json
{
  "name": "Jane Customer",
  "email": "jane@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "phone": "+255712345678",
  "first_name": "Jane",
  "last_name": "Customer",
  "registration_source": "self_registration"
}
```

**Rules (from `RegisterRequest`):** `name`, `email` (unique), `password` (+ confirmed, min 8); `phone` optional E.164; `registration_source` optional enum.

**Success (201)**

```json
{
  "success": true,
  "message": "Registration successful",
  "token": "{plainTextSanctumToken}",
  "token_type": "Bearer",
  "data": {
    "id": "…",
    "name": "Jane Customer",
    "email": "jane@example.com",
    "phone": "+255712345678",
    "is_active": true,
    "email_verified_at": null,
    "roles": [],
    "created_at": "…",
    "updated_at": "…"
  }
}
```

**Important:** `token` and `token_type` remain **top-level** (not nested under `data`).

**Errors**

| HTTP | `code` | When |
|------|--------|------|
| 422 | `validation_failed` | Invalid / duplicate email, weak password, etc. |

---

## 2.2 Login

| | |
|--|--|
| **Method** | `POST` |
| **Route** | `/api/v1/login` |
| **Auth** | Public (throttled: `customer-login`) |

**Request example**

```json
{
  "email": "jane@example.com",
  "password": "Password123!"
}
```

**Success (200)**

```json
{
  "success": true,
  "message": "Login successful",
  "token": "{plainTextSanctumToken}",
  "token_type": "Bearer",
  "data": { /* UserResource */ }
}
```

**Errors**

| HTTP | `code` | When |
|------|--------|------|
| 422 | `invalid_credentials` | Bad email/password (`LoginUserAction`) |
| 403 | `account_disabled` | User exists but `is_active` is false |
| 422 | `validation_failed` | Missing/invalid email or password format |

---

## 2.3 Current user

| | |
|--|--|
| **Method** | `GET` |
| **Route** | `/api/v1/me` |
| **Auth** | Required |

**Success (200)**

```json
{
  "success": true,
  "data": { /* UserResource */ }
}
```

**Errors**

| HTTP | `code` |
|------|--------|
| 401 | `unauthenticated` |

---

## 2.4 Logout

| | |
|--|--|
| **Method** | `POST` |
| **Route** | `/api/v1/logout` |
| **Auth** | Required |

**Success (200)**

```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": null
}
```

**Errors**

| HTTP | `code` |
|------|--------|
| 401 | `unauthenticated` |

---

## Related (implemented, optional for MVP)

| Method | Route | Notes |
|--------|-------|-------|
| `POST` | `/api/v1/auth/forgot-password` | Password reset request |
| `POST` | `/api/v1/auth/reset-password` | Password reset confirm |
| `GET` / `POST` | `/api/v1/account/email/verify…` | Email verification |

**Future:** dedicated mobile refresh-token rotation policy (today: Sanctum personal access tokens with multi-device support).

---

# 3. Home / CMS

Public CMS endpoints (no customer auth required). Controllers: `StorefrontCmsHomepageController`, `StorefrontCmsNavigationController`.

## 3.1 Homepage

| | |
|--|--|
| **Method** | `GET` |
| **Route** | `/api/v1/storefront/homepage` |

**Query parameters**

| Param | Required | Values |
|-------|----------|--------|
| `commerce_context` | Yes | `CHINA_IMPORT`, `TZ_LOCAL`, `GLOBAL` |
| `allow_global_fallback` | No | boolean (default `true`) |

**Behavior (implemented):** Resolves active `CmsCampaign` for the exact commerce context → campaign layout; else default layout; optional `GLOBAL` layout fallback. **Never mixes** `CHINA_IMPORT` and `TZ_LOCAL` layouts.

**Success shape**

```json
{
  "success": true,
  "data": {
    "id": "…",
    "name": "…",
    "slug": "…",
    "commerce_context": "CHINA_IMPORT",
    "status": "active",
    "is_default": true,
    "sections": [
      {
        "/* section types include */": "HERO, FEATURED_PRODUCTS, FEATURED_COLLECTIONS, CAMPAIGN-driven sections, …"
      }
    ]
  },
  "meta": {
    "commerce_context": "CHINA_IMPORT",
    "resolved_commerce_context": "CHINA_IMPORT",
    "allow_global_fallback": true,
    "used_global_fallback": false,
    "campaign": {
      "id": "…",
      "name": "…",
      "slug": "…",
      "priority": 10,
      "promotion_ids": []
    }
  }
}
```

If no layout exists, `data` may be `null` with a meta `message`.

**Section types (enum `CmsHomepageSectionType`):** includes `HERO`, `FEATURED_PRODUCTS`, `FEATURED_COLLECTIONS`, `FEATURED_BRANDS`, `FEATURED_CATEGORIES`, `FLASH_DEALS`, `SHOP_BY_STORE`, banners, trust blocks, etc. Mobile should render known types and ignore unknown ones safely.

## 3.2 Navigation

| | |
|--|--|
| **Method** | `GET` |
| **Route** | `/api/v1/storefront/navigation` |

**Query parameters**

| Param | Required | Values |
|-------|----------|--------|
| `commerce_context` | Yes | `CHINA_IMPORT`, `TZ_LOCAL`, `GLOBAL` |
| `navigation_type` | No | `PRIMARY`, `FOOTER`, `MOBILE`, `UTILITY` |
| `audience` | No | `guest`, `authenticated`, `admin_preview` (default `guest`) |
| `hydrate_mega_menus` | No | boolean (default `true`) |

**Success**

```json
{
  "success": true,
  "data": { /* navigation tree / items from ResolveStorefrontNavigationAction */ }
}
```

Prefer `navigation_type=MOBILE` for app shells when CMS content is configured for that type.

## 3.3 Hero / featured / campaigns

- **Hero & featured products:** delivered as homepage **sections** inside `GET /storefront/homepage` (`HERO`, `FEATURED_PRODUCTS`, …), not separate public routes.
- **Campaigns:** selected server-side; campaign summary appears in homepage `meta.campaign` when an active campaign wins resolution.
- **Additional public helpers:** `GET /api/v1/storefront/maintenance`, `GET /api/v1/features/public` (feature flags / maintenance — not CMS layout).

---

# 4. Catalog

Keep journeys separate on the client. Cart/checkout reject mixed channels.

## 4.1 CHINA_IMPORT (Order from China)

**Controllers:** `ChinaStorefrontController`, shared product APIs on `CustomerProductController`.

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/api/v1/storefront/china/menu` | Mega-menu style payload: categories, brands, featured products (`?category=` optional) |
| `GET` | `/api/v1/storefront/china/categories` | China navigation categories |
| `GET` | `/api/v1/storefront/china/featured-collections` | Featured collection categories |
| `GET` | `/api/v1/storefront/china/brands` | Brands (`?category=` optional) |
| `GET` | `/api/v1/storefront/china/products` | Product cards (`category`, `brand`, `featured`, `search`, `page`, `per_page`) |
| `GET` | `/api/v1/products/{product:slug}` | Product detail (shared) |
| `GET` | `/api/v1/products/{product:slug}/configuration` | Variant / configuration matrix |
| `POST` | `/api/v1/products/{product:slug}/quote` | Quote helper (throttled) |

**Departments:** There is **no** public customer `GET /departments` route today. China customer taxonomy is **category-based** (`storefront/china/categories` / menu). Admin department APIs exist but are **not** part of the mobile customer contract. (**Future** if mobile needs department browsing.)

**Variants:** Use `GET /products/{slug}/configuration` (and cart add with `product_variant_id` when the sell path is Variant).

**Example — China products list**

```http
GET /api/v1/storefront/china/products?category=electronics&per_page=24&page=1
```

Response uses Laravel Resource collection + `success: true` (paginated when applicable).

---

## 4.2 TZ_LOCAL (Buy from TZ)

**Controller:** `TzStorefrontController` under prefix `storefront/tz`.

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/api/v1/storefront/tz/stores` | List active TZ stores |
| `GET` | `/api/v1/storefront/tz/stores/{store}` | Store detail (`{store}` = id or slug per catalog resolver) |
| `GET` | `/api/v1/storefront/tz/stores/{store}/categories` | Store categories |
| `GET` | `/api/v1/storefront/tz/stores/{store}/products` | Store products (`category`, `search`, `page`, `per_page`) |
| `GET` | `/api/v1/storefront/tz/stores/{store}/products/{product}` | Store-scoped product detail |

Also available (legacy/shared storefront):

| Method | Route |
|--------|-------|
| `GET` | `/api/v1/stores` |
| `GET` | `/api/v1/stores/{slug}` |

**Variants:** Prefer store product detail + shared `GET /products/{slug}/configuration` when the product is purchasable on the Variant path.

---

## 4.3 Shared catalog helpers

| Method | Route | Notes |
|--------|-------|-------|
| `GET` | `/api/v1/products` | General product index |
| `GET` | `/api/v1/categories` | Categories |
| `GET` | `/api/v1/categories/{slug}` | Category detail |
| `GET` | `/api/v1/brands` | Brands |
| `GET` | `/api/v1/shipping/durations` | Shipping duration metadata |

Mobile MVP should prefer **journey-scoped** storefront routes (`storefront/china/*`, `storefront/tz/*`) plus product detail/configuration.

---

# 5. Search

**Controller:** `App\Http\Controllers\Search\MarketplaceSearchController`  
**Service:** `UnifiedMarketplaceSearchService`

## 5.1 Suggest

| | |
|--|--|
| **Method** | `GET` |
| **Route** | `/api/v1/search/suggest` |
| **Auth** | Public |

**Parameters**

| Param | Type | Notes |
|-------|------|-------|
| `q` | string | max 64, nullable |
| `scope` | string | `all` \| `china` \| `tz` (default `all`) |
| `limit_products` | int | 1–24 |
| `limit_brands` | int | 0–8 |
| `limit_stores` | int | 0–8 |
| `limit_categories` | int | 0–8 |

**Success**

```json
{
  "success": true,
  "data": { /* suggest buckets: products, brands, stores, categories (shape from service) */ }
}
```

## 5.2 Full product search

| | |
|--|--|
| **Method** | `GET` |
| **Route** | `/api/v1/search/products` |
| **Auth** | Public |

**Parameters**

| Param | Type | Notes |
|-------|------|-------|
| `q` | string | max 64, nullable (empty → empty results) |
| `scope` | string | `all` \| `china` \| `tz` (default `all`) |
| `page` | int | min 1 (default 1) |
| `per_page` | int | 1–48 (default 24) |
| `sort` | string | `relevance` \| `newest` (default `relevance`) |

**Success**

```json
{
  "success": true,
  "data": [ /* product hits */ ],
  "meta": { /* pagination / search meta from service */ }
}
```

**Scope mapping (client guidance):**

| `scope` | Corpus |
|---------|--------|
| `all` | Combined marketplace |
| `china` | CHINA_IMPORT-oriented results |
| `tz` | TZ_LOCAL / store-oriented results |

---

# 6. Cart

**Controller:** `App\Http\Controllers\CartController`  
**Auth:** Required for all cart routes.  
**Important:** **No guest cart** is implemented. Unauthenticated cart calls return `401` / `unauthenticated`.

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| `GET` | `/api/v1/cart` | 200 | Current active cart (`CartResource`) |
| `POST` | `/api/v1/cart/items` | 201 | Add / merge line |
| `PUT` / `PATCH` | `/api/v1/cart/items/{item}` | 200 | Update quantity |
| `DELETE` | `/api/v1/cart/items/{item}` | 200 | Remove line |
| `DELETE` | `/api/v1/cart/clear` | 200 | Clear cart |
| `DELETE` | `/api/v1/cart` | 200 | Alias of clear |
| `POST` | `/api/v1/cart/buy-now` | 201 | Buy-now checkout preparation (**optional** for MVP) |

### Add item — request example

```json
{
  "product_variant_id": "uuid",
  "quantity": 1
}
```

Alternate simple-product path:

```json
{
  "product_id": "uuid",
  "quantity": 1
}
```

Optional: `currency`, `shipping_method` (`air`|`sea`) when applicable. Aliases `variant_id` / `configuration_id` are accepted and merged to `product_variant_id`.

### Success shape

```json
{
  "success": true,
  "data": {
    "/* CartResource */": "items, currency, item_count, subtotal, total, is_empty, …"
  }
}
```

### Error codes (cart)

| `code` | Typical HTTP | Examples |
|--------|--------------|----------|
| `unauthenticated` | 401 | Guest access |
| `validation_failed` | 422 | FormRequest (`quantity` min 1, invalid UUID/`exists`) |
| `business_rule_violated` | 422 | Inactive/unavailable variant, stock, mixed CHINA/TZ cart, purchasability |
| `not_found` | 404 | Another customer’s cart item; missing owned item |

---

# 7. Checkout

**Controller:** `App\Http\Controllers\CheckoutController`  
**Auth:** Required.

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/api/v1/checkout` | Checkout preview (same prepare surface) |
| `POST` | `/api/v1/checkout/prepare` | Address + cart preview (**does not** create order) |
| `POST` | `/api/v1/checkout/start` | Start checkout session (**201**) |
| `GET` | `/api/v1/checkout/{checkoutSession}` | Show session |
| `POST` | `/api/v1/checkout/{checkoutSession}/refresh` | Revalidate / refresh totals |
| `POST` | `/api/v1/checkout/{checkoutSession}/shipping-choice` | Save shipping choice |
| `DELETE` | `/api/v1/checkout/{checkoutSession}` | Cancel session |

### Prepare success

```json
{
  "success": true,
  "message": "Checkout prepared successfully.",
  "data": { /* CheckoutResource: cart, delivery_address, totals, shipping_summary, … */ }
}
```

### Start success (201)

```json
{
  "success": true,
  "message": "Checkout session started.",
  "data": { /* CheckoutSessionResource */ }
}
```

### Shipping choice — request example

```json
{
  "shipping_choice": "customer_agent",
  "shipping_method": null,
  "agent_name": null,
  "agent_contact": null
}
```

**`shipping_choice` values (`DeliveryType`):**

| Value | Typical channel |
|-------|-----------------|
| `company_shipping` | CHINA_IMPORT (requires `shipping_method` `air` or `sea`) |
| `customer_agent` | CHINA_IMPORT |
| `self_pickup` | TZ_LOCAL |
| `negotiated_delivery` | TZ_LOCAL |

### Create order after checkout (customer orders API)

| Method | Route |
|--------|-------|
| `POST` | `/api/v1/orders/from-checkout/{checkoutSession}` |
| `POST` | `/api/v1/orders/confirm` | Compatibility facade (may require shipping fields) |

### `business_rule_violated` examples (checkout)

| Case | Typical field |
|------|----------------|
| Expired session | `session` — “Checkout session has expired…” |
| Completed / non-cancellable session | `session` |
| Empty cart / missing active cart | `cart` |
| Missing delivery address (prepare) | `delivery_address` |
| Mixed CHINA_IMPORT + TZ_LOCAL cart | `cart` |
| Invalid / disallowed shipping choice for channel | `shipping_choice` |
| Stale fingerprint / pricing mismatch at order time | `session` — “Checkout totals are stale…” |
| Company shipping without price | `shipping` / `shipping_method` |

FormRequest enum/format failures remain `validation_failed`. Cross-user session access → `404` / `not_found`.

---

# 8. Payments

**Controllers:** `CheckoutPaymentMethodsController`, `PaymentOrchestratorController`  
**Auth:** Customer payment routes require Sanctum **except** browser return reconcile (proof-based, unauthenticated).

## 8.1 Payment methods

| | |
|--|--|
| **Method** | `GET` |
| **Route** | `/api/v1/payments/methods` |
| **Auth** | Required |

Returns enabled methods + availability (no secrets) from `PaymentConfigurationResolver::presentCheckoutAvailability()`.

```json
{
  "success": true,
  "data": {
    "default_provider": "nmb",
    "enabled_methods": ["nmb", "cash"],
    "methods": [
      { "code": "nmb", "enabled": true, "available": true, "selectable": true }
    ]
  }
}
```

## 8.2 Start payment

| | |
|--|--|
| **Method** | `POST` |
| **Route** | `/api/v1/payments/start/{order}` |
| **Auth** | Required |

**Request**

```json
{
  "provider": "nmb"
}
```

`provider` optional; server resolves default from settings when omitted.

**Success (201)** — `PaymentTransactionResource` fields include:

- `id`, `order_id`, `provider`, `merchant_reference`
- `checkout_url` (Hosted Checkout redirect)
- `status`, `amount`, `currency`
- `provider_reference`, `success_indicator`
- nested `order` when loaded

```json
{
  "success": true,
  "message": "Payment transaction started.",
  "data": {
    "id": "…",
    "checkout_url": "https://…",
    "merchant_reference": "COTZ-PAY-…",
    "status": "processing",
    "amount": "25000.00",
    "currency": "TZS"
  }
}
```

## 8.3 Payment status / refresh

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/api/v1/payments/{paymentTransaction}` | Show transaction |
| `POST` | `/api/v1/payments/{paymentTransaction}/refresh` | Provider verify + completion pipeline |
| `POST` | `/api/v1/payments/{paymentTransaction}/nmb/checkout-session` | Fresh NMB Hosted Checkout session for retryable txns |

## 8.4 Return / reconcile

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| `GET` | `/api/v1/payments/return-context` | Customer | Resolve txn by `order_id` and/or `merchant_reference` |
| `POST` | `/api/v1/payments/nmb/return-reconcile` | **Public** (proof-based) | Browser return reconciliation |

**Reconcile body (`ReconcileNmbBrowserReturnRequest`):**

```json
{
  "payment_transaction_id": "uuid",
  "merchant_reference": "COTZ-PAY-…",
  "success_indicator": "…",
  "result_indicator": "…",
  "order_id": "uuid"
}
```

Failed proof → uniform **404** / `not_found` (no existence leak).

## 8.5 Internal NMB surfaces (not for mobile business logic)

These exist for the **gateway / ops**, not as mobile product APIs. Do not redesign them in the app:

| Route | Role |
|-------|------|
| `POST /api/v1/payments/nmb/callback` | NMB callback (`NmbPaymentCallbackController`) |
| `POST /api/v1/webhooks/nmb` | NMB webhook (`NmbWebhookController`) |

**Mobile guidance:** Start payment → open `checkout_url` → on return call `return-context` and/or `return-reconcile` + `refresh` as needed. Treat callbacks/webhooks as **server-owned**.

## 8.6 Payment error codes

| `code` | Typical cases |
|--------|----------------|
| `payment_failed` | Provider disabled/unavailable, gateway initiation failure, unregistered provider |
| `business_rule_violated` | Order not payable, shipping not ready, non-retryable session, active conflicting payment |
| `validation_failed` | Invalid request body / enum |
| `unauthenticated` | Missing customer token (authenticated payment routes) |
| `not_found` | Ownership / proof failures |

**Legacy:** `POST /api/v1/payments/{payment}/initiate` returns **410** retired (`business_rule_violated` + `deprecated` / `replacement`). Prefer orchestrator start.

Optional bank-transfer prep: `POST /api/v1/orders/{order}/payments` (NMB via this path is rejected — must use `/payments/start/{order}`).

---

# 9. Orders

**Controller:** `App\Http\Controllers\CustomerOrderController`  
**Auth:** Required.

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/api/v1/orders` | Paginated list |
| `GET` | `/api/v1/orders/{order}` | Detail |
| `GET` | `/api/v1/orders/{order}/tracking` | Progress / tracking timeline |
| `POST` | `/api/v1/orders/{order}/cancel` | Customer cancel (when allowed) |
| `POST` | `/api/v1/orders/from-checkout/{checkoutSession}` | Create order from session |
| `POST` | `/api/v1/orders/confirm` | Compatibility confirm |

### List query

| Param | Values |
|-------|--------|
| `filter` | `all` \| `active` \| `completed` |
| `page` | ≥ 1 |
| `per_page` | 1–100 (default 10) |

### Pagination format (list)

```json
{
  "success": true,
  "data": [ /* CustomerOrderResource cards */ ],
  "links": {
    "first": "…",
    "last": "…",
    "prev": null,
    "next": "…"
  },
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 12
  }
}
```

### Detail / tracking

- Detail: `CustomerOrderDetailResource` — items, payment snapshot, summary, progress, etc.
- Tracking: progress keys/labels, `timeline` / `unified_timeline`, optional shipment or customer-agent pickup payload.

### Cancel

```http
POST /api/v1/orders/{order}/cancel
```

Optional body: `{ "reason": "Changed mind" }`.

Non-cancellable states → `422` / `business_rule_violated`. Other customer’s order → `404` / `not_found`.

---

# 10. Error Contract

| Code | Meaning | Client action |
|------|---------|---------------|
| `unauthenticated` | Missing/invalid customer token | Prompt login; clear stale token |
| `validation_failed` | Request field validation failed | Show field errors from `errors` |
| `invalid_credentials` | Login email/password mismatch | Show login error; do not reveal which field is wrong beyond message |
| `account_disabled` | Account inactive | Block login; show support message |
| `not_found` | Resource missing or not owned (often opaque) | Navigate away / refresh lists |
| `business_rule_violated` | Domain rule (cart mix, stock, shipping, cancel window, stale checkout) | Show `message` / field errors; offer refresh/retry path |
| `payment_failed` | Payment provider/domain failure | Offer retry / another method; do not mark order paid |
| `feature_disabled` | Feature flag / capability off | Hide UI; optional message |
| `forbidden` | Authorization failed (non-authz customer cases) | Stop action |
| `maintenance_mode` | HTTP 503 maintenance | Show maintenance screen |
| `server_error` | Unexpected server failure | Retry later; log `request_id` |

**Envelope reminder:** prefer reading `code` for branching; always keep showing `message` to users; use `errors` for form highlighting when present.

---

# 11. Mobile MVP Flow

Recommended happy path against **current** APIs:

1. **Splash** — optional `GET /features/public` / maintenance check.  
2. **Login / Register** — `POST /login` or `POST /register`; persist top-level `token` + `token_type=Bearer`.  
3. **Home** — `GET /storefront/homepage?commerce_context=CHINA_IMPORT|TZ_LOCAL` + `GET /storefront/navigation?commerce_context=…&navigation_type=MOBILE`.  
4. **Search** — `GET /search/suggest` while typing; `GET /search/products` for results (`scope=all|china|tz`).  
5. **Product detail** — journey list → `GET /products/{slug}` + `GET /products/{slug}/configuration` (or TZ store product route).  
6. **Cart** — authenticated `POST /cart/items` … `GET /cart` (no guest cart).  
7. **Checkout** — `POST /checkout/prepare` → `POST /checkout/start` → `POST /checkout/{id}/shipping-choice` → `POST /orders/from-checkout/{id}`.  
8. **Payment** — `GET /payments/methods` → `POST /payments/start/{order}` → open `checkout_url` → `return-context` / `return-reconcile` + `refresh` as needed.  
9. **Orders** — `GET /orders`, `GET /orders/{id}`, `GET /orders/{id}/tracking`; cancel when allowed.  
10. **Account** — `GET /me`, `GET|PATCH /profile`, address endpoints under `/profile` and `/account/addresses` (see routes for full account surface).

### Journey separation checklist

- Home CMS context matches the journey the user selected.  
- Catalog browsing stays on `storefront/china/*` or `storefront/tz/*`.  
- Never add both China and TZ SKUs to the same cart (server will reject with `business_rule_violated`).  
- Shipping choice must match channel (`company_shipping` / `customer_agent` vs `self_pickup` / `negotiated_delivery`).

---

## Appendix A — Controllers quick reference

| Area | Primary controller |
|------|-------------------|
| Auth | `AuthController` |
| CMS home / nav | `StorefrontCmsHomepageController`, `StorefrontCmsNavigationController` |
| China catalog | `ChinaStorefrontController` |
| TZ catalog | `TzStorefrontController` |
| Search | `MarketplaceSearchController` |
| Cart | `CartController` |
| Checkout | `CheckoutController` |
| Payments | `PaymentOrchestratorController`, `CheckoutPaymentMethodsController` |
| Orders | `CustomerOrderController` |
| Contract helper | `App\Support\Http\ApiResponse` |

## Appendix B — Explicitly out of mobile MVP scope (exist but admin/ops)

- All `/api/v1/admin/*` routes  
- POS endpoints  
- NMB callback/webhook payload contracts (internal)  
- Guest cart (**not implemented**)  

---

*End of Mobile API Contract v1.*
