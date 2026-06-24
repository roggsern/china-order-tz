# CHINA ORDER TZ — Master Specification

**Document Version:** 1.0.0  
**Last Updated:** June 25, 2026  
**Status:** Authoritative — Single Source of Truth  
**Maintainer:** CHINA ORDER TZ Engineering

This document defines the vision, architecture, standards, and functional scope for the CHINA ORDER TZ platform. All product, design, and engineering decisions must align with this specification. When implementation diverges, update this document first.

---

## Table of Contents

1. [Project Vision](#project-vision)
2. [Business Goals](#business-goals)
3. [Target Users](#target-users)
4. [Brand Identity](#brand-identity)
5. [Color Palette](#color-palette)
6. [Typography](#typography)
7. [Folder Structure](#folder-structure)
8. [Frontend Architecture](#frontend-architecture)
9. [Backend Architecture](#backend-architecture)
10. [Database Design](#database-design)
11. [API Architecture](#api-architecture)
12. [Authentication](#authentication)
13. [User Roles](#user-roles)
14. [Ecommerce Modules](#ecommerce-modules)
15. [China Order Module](#china-order-module)
16. [AI Features](#ai-features)
17. [UI Rules](#ui-rules)
18. [Coding Standards](#coding-standards)
19. [Security Standards](#security-standards)
20. [Performance Standards](#performance-standards)
21. [SEO Standards](#seo-standards)
22. [Future Roadmap](#future-roadmap)

---

# Project Vision

CHINA ORDER TZ is a dual-mode commerce platform that connects Tanzanian customers with products sourced directly from China. The platform serves two complementary journeys:

1. **Standard Ecommerce** — Browse, search, and purchase curated products from a local catalog with transparent pricing in Tanzanian Shillings (TZS), secure checkout, and reliable delivery.
2. **China Order Service** — Submit product links from Alibaba, 1688, or Taobao, upload reference images, and receive professional quotations before placing custom import orders.

The long-term vision is to become Tanzania's most trusted gateway for China-sourced goods — combining factory-direct pricing, verified suppliers, AI-assisted discovery, and end-to-end logistics transparency in a single, modern digital experience.

**Tagline:** *Import Products Directly From China — Fast • Trusted • Affordable*

---

# Business Goals

| Goal | Description | Success Metric |
|------|-------------|----------------|
| Market penetration | Establish CHINA ORDER TZ as a recognized import-commerce brand in Tanzania | Monthly active users, brand recall |
| Revenue growth | Drive GMV through catalog sales and high-margin China order services | Gross merchandise value (GMV), average order value (AOV) |
| Operational efficiency | Automate quotation, sourcing, and order fulfillment workflows | Quotation turnaround time, order processing SLA |
| Customer trust | Deliver quality products, accurate pricing, and transparent tracking | NPS, repeat purchase rate, review ratings |
| Cost advantage | Pass factory-direct savings to customers vs. local retail | Price competitiveness index |
| Payment accessibility | Support locally preferred payment methods (M-Pesa, cards, bank transfer) | Payment success rate, checkout conversion |
| Scalable infrastructure | Build on a maintainable monorepo that supports rapid feature delivery | Deployment frequency, uptime SLA (99.9%) |

**Primary revenue streams:**

- Margin on curated catalog products
- Service fees on China order quotations and fulfillment
- Shipping and logistics fees (air and sea freight)
- Optional premium services (expedited sourcing, bulk procurement)

---

# Target Users

## Primary: Tanzanian Consumers (Customers)

- Individuals and households seeking affordable, quality products not readily available locally
- Price-conscious shoppers comfortable with online purchasing
- Entrepreneurs and resellers sourcing inventory from China in small to medium quantities
- Users who prefer mobile-first experiences and local payment methods (M-Pesa)

## Secondary: Business Buyers

- SMEs importing building materials, electronics, fashion, or beauty products in bulk
- Retail shop owners restocking from Chinese suppliers via link-based ordering
- Organizations requiring formal quotations and invoice documentation

## Internal: Platform Administrators

- Operations staff managing catalog, orders, quotations, and supplier relationships
- Finance team reconciling payments and refunds
- Support agents handling customer inquiries and order tracking

**Geographic focus:** Tanzania (primary), with architecture prepared for East Africa expansion.

**Languages:** English (primary UI), Swahili (planned Phase 2 localization).

---

# Brand Identity

## Brand Name

**CHINA ORDER TZ**

## Brand Personality

| Attribute | Expression |
|-----------|------------|
| Trustworthy | Verified suppliers, secure payments, transparent pricing |
| Premium-accessible | Gold accent on dark hero surfaces; factory-direct value without feeling cheap |
| Efficient | Fast shipping messaging, streamlined ordering flows |
| Global-local | China sourcing expertise with Tanzania-first UX (TZS, M-Pesa, local support) |

## Logo Mark

- Monogram: Bold **"C"** on a gold gradient rounded square (`#c9a227` → `#8b6914`)
- Wordmark: **ORDER** in dark zinc, **TZ** in gold
- Sub-label: **CHINA** in small caps with wide letter-spacing

## Voice & Tone

- Clear, confident, and helpful — never overly technical
- Emphasize speed, trust, and affordability in customer-facing copy
- Use active voice; avoid jargon in consumer flows
- Admin and quotation communications remain professional and precise

## Imagery Guidelines

- Hero and marketing surfaces: dark backgrounds (`zinc-950`) with gold highlights
- Product photography: clean, well-lit, consistent aspect ratios
- Category cards: gradient overlays with descriptive iconography
- Avoid stock imagery that contradicts the China-to-Tanzania import narrative

---

# Color Palette

All colors are defined as CSS custom properties in `apps/web/src/app/globals.css` and must be used consistently across web and admin interfaces.

## Primary Brand Colors

| Token | Hex | Usage |
|-------|-----|-------|
| `--gold` | `#c9a227` | Primary CTA buttons, brand accents, badges, links |
| `--gold-light` | `#e8c547` | Hover states, gradient highlights, emphasis text |
| `--gold-dark` | `#8b6914` | Logo gradient end, pressed states, depth |

## Neutral Palette

| Token | Tailwind Equivalent | Usage |
|-------|---------------------|-------|
| `--background` | `#ffffff` | Page backgrounds, cards |
| `--foreground` | `#18181b` (zinc-900) | Body text, headings on light surfaces |
| — | `zinc-950` | Hero sections, dark marketing blocks |
| — | `zinc-800` / `zinc-700` | Borders, secondary dark UI |
| — | `zinc-500` / `zinc-400` | Muted text, placeholders |
| — | `zinc-200` / `zinc-100` | Borders, subtle backgrounds |

## Semantic Colors

| Purpose | Color | Usage |
|---------|-------|-------|
| Success | Emerald (`emerald-500`–`600`) | Order confirmed, payment success |
| Warning | Amber (`amber-500`) | Low stock, pending actions |
| Error | Red (`red-600`) | Validation errors, failed payments |
| Info | Blue (`blue-600`) | Informational banners, links |
| Accent (promotional) | Red (`red-600`) | Limited offers, flash deals |

## Gradient Patterns

- **Brand gradient:** `from-[#e8c547] via-[#c9a227] to-[#f5d76e]` — hero headline text
- **Logo gradient:** `from-[#c9a227] to-[#8b6914]` — logo mark background
- **Category cards:** Per-category gradients defined in catalog data (rose, slate, blue, amber, stone)

---

# Typography

## Font Stack

| Role | Font | CSS Variable | Source |
|------|------|--------------|--------|
| Sans (primary) | Geist Sans | `--font-geist-sans` | Google Fonts via `next/font` |
| Mono (code, IDs) | Geist Mono | `--font-geist-mono` | Google Fonts via `next/font` |
| System fallback | system-ui, -apple-system, sans-serif | — | Body fallback |

## Type Scale

| Element | Size | Weight | Notes |
|---------|------|--------|-------|
| Hero H1 | `text-4xl` → `text-6xl` | Bold (700) | Uppercase emphasis on key phrase via gradient |
| Section H2 | `text-2xl` → `text-3xl` | Bold (700) | Section headings |
| Card title | `text-base` → `text-lg` | Semibold (600) | Product names, category titles |
| Body | `text-sm` → `text-base` | Regular (400) | Descriptions, form labels |
| Caption / meta | `text-xs` | Medium (500) | Badges, stats, footer links |
| CTA buttons | `text-sm` | Bold (700) | Uppercase tracking-wide on primary actions |
| Brand sub-label | `text-[10px]` | Semibold (600) | Uppercase, `tracking-[0.25em]` |

## Formatting Rules

- Currency: Format all prices with `Intl.NumberFormat('en-TZ', { currency: 'TZS' })` — no decimal places for TZS
- Headings: Sentence case for admin; marketing headlines may use uppercase for emphasis
- Line height: `leading-relaxed` for body copy; `leading-tight` / `leading-[1.08]` for display headings

---

# Folder Structure

## Current Monorepo Layout

```
china-order-tz/
├── MASTER_SPECIFICATION.md      # This document — single source of truth
├── README.md
├── package.json                 # Root workspace scripts (Prettier)
├── docker-compose.yml
├── docker-compose.prod.yml
├── Makefile
├── .env.example
│
├── apps/
│   ├── web/                     # Next.js 15 storefront
│   │   ├── src/
│   │   │   ├── app/             # App Router pages and layouts
│   │   │   ├── components/      # React components (feature-grouped)
│   │   │   └── lib/             # Utilities, API client, constants
│   │   ├── public/              # Static assets
│   │   └── package.json
│   │
│   └── api/                     # Laravel 12 REST API
│       ├── app/
│       │   ├── Http/
│       │   │   ├── Controllers/
│       │   │   ├── Middleware/
│       │   │   └── Requests/
│       │   ├── Models/
│       │   ├── Services/
│       │   ├── Policies/
│       │   └── Enums/
│       ├── bootstrap/
│       ├── config/
│       ├── database/
│       │   ├── migrations/
│       │   ├── seeders/
│       │   └── factories/
│       ├── routes/
│       │   ├── api.php
│       │   └── web.php
│       └── tests/
│
├── docker/
│   ├── nginx/                   # API reverse proxy
│   ├── node/                    # Next.js container
│   └── php/                     # Laravel PHP-FPM container
│
└── .github/
    └── workflows/               # CI and Docker build pipelines
```

## Target Frontend Structure (Planned)

```
apps/web/src/
├── app/
│   ├── (storefront)/            # Public ecommerce routes
│   │   ├── page.tsx             # Homepage
│   │   ├── categories/
│   │   ├── products/
│   │   ├── cart/
│   │   ├── checkout/
│   │   ├── orders/
│   │   ├── account/
│   │   └── china-order/         # China Order module
│   ├── (auth)/                  # Login, register, password reset
│   └── (admin)/                 # Admin dashboard (Phase 2)
├── components/
│   ├── home/
│   ├── catalog/
│   ├── cart/
│   ├── checkout/
│   ├── china-order/
│   ├── ui/                      # Shared primitives (Button, Input, Modal)
│   └── layout/
├── lib/
│   ├── api/                     # Typed API client
│   ├── auth/
│   ├── hooks/
│   └── utils/
└── types/                       # Shared TypeScript interfaces
```

## Target Backend Structure (Planned)

```
apps/api/app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Auth/
│   │   ├── Catalog/
│   │   ├── Cart/
│   │   ├── Checkout/
│   │   ├── Order/
│   │   ├── ChinaOrder/
│   │   ├── Review/
│   │   ├── Wishlist/
│   │   ├── Coupon/
│   │   ├── Notification/
│   │   └── Admin/
│   ├── Resources/               # API transformers
│   └── Requests/                # Form request validation
├── Services/
│   ├── Payment/
│   ├── Shipping/
│   ├── Quotation/
│   ├── Notification/
│   └── Ai/
├── Jobs/                        # Async queue jobs
├── Events/                      # Domain events
└── Listeners/
```

---

# Frontend Architecture

## Stack

| Layer | Technology |
|-------|------------|
| Framework | Next.js 15 (App Router) |
| UI Library | React 19 |
| Language | TypeScript 5 |
| Styling | Tailwind CSS 4 |
| Bundler | Turbopack (dev and build) |
| Linting | ESLint (eslint-config-next) |
| Formatting | Prettier + prettier-plugin-tailwindcss |

## Architectural Principles

1. **Server-first rendering** — Use React Server Components (RSC) for catalog, product detail, and SEO-critical pages. Client Components only where interactivity is required (cart, checkout forms, search autocomplete).
2. **Feature-based components** — Group components by domain (`home/`, `catalog/`, `china-order/`) rather than by atomic type alone.
3. **Typed API layer** — All backend communication flows through a centralized API client in `src/lib/api/` with shared TypeScript types.
4. **Environment-driven configuration** — `NEXT_PUBLIC_API_URL` points to the Laravel API; no hardcoded backend URLs.
5. **Progressive enhancement** — Core flows (browse, view product) must work without JavaScript where feasible.

## Data Fetching Strategy

| Use Case | Strategy |
|----------|----------|
| Homepage, categories, product listing | Server Components + fetch with revalidation |
| Product detail | Server Component with ISR (`revalidate: 3600`) |
| Cart, checkout, account | Client Components + SWR or React Query |
| Real-time order tracking | Client polling or SSE (Phase 2) |
| Admin dashboard | Client-side data fetching with auth guards |

## State Management

- **Server state:** Fetch in Server Components; cache via Next.js `fetch` options
- **Client cart:** Context or lightweight store (Zustand planned) synced with API
- **Auth session:** HTTP-only cookies via Laravel Sanctum SPA authentication
- **Form state:** React controlled components; validation mirrored from API rules

## Routing Conventions

| Route Pattern | Purpose |
|---------------|---------|
| `/` | Homepage |
| `/categories` | Category listing |
| `/categories/[slug]` | Category products |
| `/products/[slug]` | Product detail |
| `/cart` | Shopping cart |
| `/checkout` | Checkout flow |
| `/orders` | Order history |
| `/orders/[id]` | Order detail and tracking |
| `/account/*` | Profile, addresses, wishlist |
| `/china-order` | China Order hub |
| `/china-order/quote/[id]` | Quotation detail |
| `/login`, `/register` | Authentication |

---

# Backend Architecture

## Stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 12 |
| Language | PHP 8.4+ |
| Database | MySQL 8.4 |
| Cache | Redis (planned) |
| Queue | Laravel Queue + Redis (planned) |
| File Storage | S3-compatible object storage (planned) |
| API Auth | Laravel Sanctum (planned) |
| Code Style | Laravel Pint |

## Architectural Principles

1. **RESTful API** — Versioned JSON API under `/api/v1`; no business logic in route closures
2. **Service layer** — Complex domain logic lives in `app/Services/`, not controllers
3. **Thin controllers** — Controllers validate input, delegate to services, return API Resources
4. **Repository pattern (selective)** — Use for complex queries; Eloquent directly for simple CRUD
5. **Event-driven side effects** — Order placed, payment confirmed, and quotation approved dispatch events handled by listeners/jobs
6. **Queue all slow work** — Email, SMS, AI inference, image processing, and third-party API calls run asynchronously

## Domain Modules

| Module | Namespace | Responsibility |
|--------|-----------|----------------|
| Auth | `App\Services\Auth` | Registration, login, tokens, password reset |
| Catalog | `App\Services\Catalog` | Categories, products, variants, inventory |
| Cart | `App\Services\Cart` | Guest and authenticated cart management |
| Checkout | `App\Services\Checkout` | Order creation, address validation |
| Payment | `App\Services\Payment` | M-Pesa, card, bank transfer gateways |
| Order | `App\Services\Order` | Order lifecycle, status transitions |
| ChinaOrder | `App\Services\ChinaOrder` | Link parsing, image upload, quotations |
| Review | `App\Services\Review` | Product reviews and moderation |
| Wishlist | `App\Services\Wishlist` | Saved products |
| Coupon | `App\Services\Coupon` | Discount codes and redemption |
| Notification | `App\Services\Notification` | Email, SMS, in-app notifications |
| Ai | `App\Services\Ai` | Search, image matching, recommendations |
| Admin | `App\Services\Admin` | Dashboard, reporting, moderation |

## Infrastructure (Docker)

| Service | Container | Port |
|---------|-----------|------|
| Frontend | `docker/node` | 3000 |
| API (Nginx) | `docker/nginx` | 8000 |
| PHP-FPM | `docker/php` | — |
| MySQL | `mysql:8.4` | 3306 |

---

# Database Design

**Engine:** MySQL 8.4  
**Charset:** `utf8mb4`  
**Collation:** `utf8mb4_unicode_ci`  
**Conventions:** Snake_case table and column names; `id` as BIGINT UNSIGNED primary key; `created_at` / `updated_at` on all tables; soft deletes (`deleted_at`) on user-facing entities.

## Planned Tables

### Core & Authentication

| Table | Purpose |
|-------|---------|
| `users` | Customer and admin accounts (email, password, role) |
| `password_reset_tokens` | Password reset tokens (Laravel default) |
| `sessions` | Server-side session storage (Laravel default) |
| `personal_access_tokens` | Sanctum API tokens |
| `user_profiles` | Extended profile data (phone, avatar, preferred language) |
| `user_addresses` | Shipping and billing addresses |

### Catalog

| Table | Purpose |
|-------|---------|
| `categories` | Product categories (name, slug, parent_id, sort_order, is_active) |
| `brands` | Product brands |
| `products` | Core product records (name, slug, description, base price, status) |
| `product_images` | Product image gallery (url, sort_order, is_primary) |
| `product_variants` | SKU-level variants (size, color, price, stock) |
| `product_attributes` | Attribute definitions (e.g., Color, Size) |
| `product_attribute_values` | Attribute value options linked to variants |
| `category_product` | Many-to-many pivot: products ↔ categories |
| `inventory_logs` | Stock adjustment audit trail |

### Cart

| Table | Purpose |
|-------|---------|
| `carts` | Cart sessions (user_id or guest_token) |
| `cart_items` | Line items (product_variant_id, quantity, unit_price) |

### Orders & Fulfillment

| Table | Purpose |
|-------|---------|
| `orders` | Order header (user_id, status, subtotal, shipping, tax, total, currency) |
| `order_items` | Order line items (product snapshot, quantity, price) |
| `order_status_history` | Status change audit log |
| `order_tracking_events` | Shipment milestones (location, description, timestamp) |
| `shipments` | Shipping method, carrier, tracking number, estimated delivery |

### Payments

| Table | Purpose |
|-------|---------|
| `payments` | Payment records (order_id, method, amount, status, gateway_ref) |
| `payment_methods` | Available payment method configuration |
| `refunds` | Refund records linked to payments |

### Reviews

| Table | Purpose |
|-------|---------|
| `reviews` | Product reviews (user_id, product_id, rating, title, body, status) |
| `review_images` | Review photo attachments |

### Wishlist

| Table | Purpose |
|-------|---------|
| `wishlists` | User wishlist container |
| `wishlist_items` | Saved product/variant references |

### Coupons

| Table | Purpose |
|-------|---------|
| `coupons` | Discount definitions (code, type, value, usage limits, expiry) |
| `coupon_usages` | Redemption records per user/order |

### Notifications

| Table | Purpose |
|-------|---------|
| `notifications` | In-app notification records (Laravel notifications table) |
| `notification_preferences` | User opt-in/out per channel and type |

### China Order Module

| Table | Purpose |
|-------|---------|
| `china_order_requests` | Customer import request header (user_id, status, notes) |
| `china_order_items` | Individual items within a request (description, quantity, specs) |
| `china_order_source_links` | Parsed supplier URLs (platform: alibaba/1688/taobao, url, metadata) |
| `china_order_attachments` | Uploaded reference images (file path, mime, size) |
| `china_order_quotes` | Admin-generated quotations (itemized costs, shipping, validity) |
| `china_order_quote_items` | Line items within a quotation |
| `china_order_status_history` | Quotation and request status audit trail |

### AI Features

| Table | Purpose |
|-------|---------|
| `ai_search_logs` | Query logs for product search analytics |
| `product_embeddings` | Vector embeddings for semantic and image search |
| `ai_recommendations` | Precomputed recommendation sets per user/session |
| `ai_image_search_sessions` | Image upload search session metadata |

### System & CMS

| Table | Purpose |
|-------|---------|
| `settings` | Key-value platform configuration |
| `pages` | CMS static pages (About, Shipping Info, Privacy Policy) |
| `media` | Centralized media library metadata |
| `audit_logs` | Admin action audit trail |
| `jobs` | Laravel queue jobs (default migration) |
| `job_batches` | Laravel job batches |
| `failed_jobs` | Failed queue job records |
| `cache` | Laravel cache store (default migration) |
| `cache_locks` | Laravel cache locks |

**Total planned tables: 47**

---

# API Architecture

## Base URL & Versioning

- **Base path:** `/api/v1`
- **Health check:** `GET /api/v1/health`
- **Laravel health:** `GET /up`
- **Versioning strategy:** URL path versioning; breaking changes require `/api/v2`

## Request / Response Format

- **Content-Type:** `application/json`
- **Charset:** UTF-8
- **Date format:** ISO 8601 (`2026-06-25T12:00:00+03:00`)
- **Currency:** Integer minor units avoided; store TZS as DECIMAL(12,2) or integer whole shillings (implementation decision: whole TZS integers)
- **Pagination:** Cursor-based for large lists; offset-based for admin tables

## Standard Response Envelope

```json
{
  "data": {},
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 100
  },
  "message": "Optional human-readable message"
}
```

## Standard Error Envelope

```json
{
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## HTTP Status Codes

| Code | Usage |
|------|-------|
| 200 | Successful GET, PUT, PATCH |
| 201 | Successful POST (resource created) |
| 204 | Successful DELETE |
| 400 | Malformed request |
| 401 | Unauthenticated |
| 403 | Forbidden (insufficient role) |
| 404 | Resource not found |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Server error |

## Endpoint Groups (Planned)

| Group | Prefix | Auth |
|-------|--------|------|
| Health | `/health` | Public |
| Auth | `/auth` | Public / Authenticated |
| Catalog | `/categories`, `/products`, `/brands` | Public |
| Cart | `/cart` | Guest token or Authenticated |
| Checkout | `/checkout` | Authenticated |
| Orders | `/orders` | Authenticated |
| Payments | `/payments` | Authenticated |
| Reviews | `/products/{id}/reviews` | Public read; Authenticated write |
| Wishlist | `/wishlist` | Authenticated |
| Coupons | `/coupons/validate` | Authenticated |
| Notifications | `/notifications` | Authenticated |
| China Order | `/china-orders` | Authenticated |
| AI | `/ai/search`, `/ai/recommendations` | Public / Authenticated |
| Admin | `/admin/*` | Admin role |

## Rate Limiting

| Tier | Limit |
|------|-------|
| Public catalog | 120 requests/minute per IP |
| Authenticated | 300 requests/minute per user |
| Auth endpoints (login/register) | 10 requests/minute per IP |
| AI search | 30 requests/minute per user |
| Admin | 600 requests/minute per admin |

---

# Authentication

## Strategy

- **Primary:** Laravel Sanctum token-based authentication for SPA (Next.js)
- **Session:** HTTP-only, Secure, SameSite=Lax cookies for web storefront
- **Guest cart:** Anonymous session token stored in cookie/localStorage, merged on login

## Flows

| Flow | Method | Endpoint |
|------|--------|----------|
| Register | POST | `/api/v1/auth/register` |
| Login | POST | `/api/v1/auth/login` |
| Logout | POST | `/api/v1/auth/logout` |
| Refresh token | POST | `/api/v1/auth/refresh` |
| Forgot password | POST | `/api/v1/auth/forgot-password` |
| Reset password | POST | `/api/v1/auth/reset-password` |
| Verify email | GET | `/api/v1/auth/verify-email/{id}/{hash}` |
| Current user | GET | `/api/v1/auth/me` |

## Password Policy

- Minimum 8 characters
- At least one uppercase letter, one lowercase letter, and one number
- Bcrypt hashing via Laravel default (`password` cast)

## Multi-Factor Authentication

- Planned Phase 3: SMS OTP via local Tanzanian SMS gateway

---

# User Roles

## Admin

**Purpose:** Full platform management and operational control.

**Capabilities:**

- Manage catalog (categories, products, variants, inventory, brands)
- Process and respond to China order quotation requests
- Manage orders (status updates, refunds, shipment tracking)
- Moderate reviews and user-generated content
- Create and manage coupons and promotions
- View analytics, reports, and audit logs
- Manage users (view, suspend, assign roles)
- Configure payment methods, shipping rates, and platform settings
- Send manual notifications to customers

**Access:** `/admin/*` routes (API and dashboard UI)

## Customer

**Purpose:** Browse, purchase, and manage personal account activity.

**Capabilities:**

- Browse catalog, search products, view categories
- Add items to cart and wishlist
- Complete checkout with supported payment methods
- View order history and track shipments
- Submit product reviews (verified purchase required)
- Submit China order requests (links, images) and accept/reject quotations
- Manage profile, addresses, and notification preferences
- Apply coupon codes at checkout

**Access:** Storefront routes; authenticated API endpoints scoped to own resources

**Role enforcement:** Laravel Policies + middleware (`role:admin`, `role:customer`); admin inherits no customer-scoped restrictions on admin routes

---

# Ecommerce Modules

## Homepage

**Purpose:** Primary entry point; communicate brand value and drive conversion.

**Sections (current implementation):**

- Sticky header with logo, search, navigation, cart, login
- Hero with brand messaging, CTAs ("Start Shopping", "Order From China"), social proof stats
- Category grid (Women's Fashion, Men's Fashion, Electronics, Beauty, Building Materials)
- Featured products carousel/grid with pricing in TZS
- "Why Choose Us" value propositions (Fast Shipping, Trusted Suppliers, Affordable Prices, Secure Payments)
- Newsletter signup
- Footer with shop, company, and support links

**Planned enhancements:** Personalized product recommendations, active promotions banner, China Order CTA module, recently viewed products.

## Categories

**Purpose:** Hierarchical product discovery.

**Features:**

- Top-level and nested category navigation
- Category landing pages with filters (price, brand, rating)
- SEO-friendly slugs (`/categories/electronics`)
- Category images and descriptive copy
- Product count per category
- Sort options: relevance, price (asc/desc), newest, best selling

## Products

**Purpose:** Detailed product presentation and purchase initiation.

**Features:**

- Product detail page with image gallery, variants, pricing, stock status
- Related and recommended products
- Add to cart and add to wishlist actions
- Customer reviews and ratings summary
- Structured data (JSON-LD Product schema)
- Share functionality
- Bulk pricing tiers (Phase 2)

## Cart

**Purpose:** Temporary holding area before checkout.

**Features:**

- Guest and authenticated cart persistence
- Quantity adjustment and item removal
- Real-time subtotal, shipping estimate, and discount preview
- Cart merge on login (guest → authenticated)
- Stock validation on cart load and checkout
- "Save for later" to wishlist
- Coupon code application

## Checkout

**Purpose:** Convert cart to confirmed order.

**Flow:**

1. Shipping address selection or creation
2. Shipping method selection (standard air, express air, sea freight)
3. Payment method selection
4. Order review and coupon application
5. Place order → payment initiation
6. Confirmation page with order number

**Validation:** Server-side validation of stock, pricing, and coupon eligibility at order creation.

## Payments

**Purpose:** Secure, locally accessible payment processing.

**Supported methods (planned):**

| Method | Provider | Priority |
|--------|----------|----------|
| M-Pesa | Vodacom M-Pesa API | P0 |
| Mobile Money (Tigo Pesa, Airtel Money) | Aggregator | P1 |
| Debit/Credit Card | Stripe or local gateway | P1 |
| Bank Transfer | Manual verification | P2 |

**Requirements:**

- Idempotent payment initiation (prevent double charge)
- Webhook handling for async payment confirmation
- Payment status: `pending`, `processing`, `completed`, `failed`, `refunded`
- Receipt generation (email + downloadable PDF)

## Orders

**Purpose:** Post-purchase order management for customers and admins.

**Customer features:**

- Order history list with status badges
- Order detail with itemized breakdown
- Invoice/receipt download
- Cancel order (within allowed window)
- Request return/refund

**Admin features:**

- Order queue with filters by status, date, payment method
- Manual status updates
- Refund processing
- Export orders (CSV)

**Order statuses:** `pending`, `confirmed`, `processing`, `shipped`, `in_transit`, `delivered`, `cancelled`, `refunded`

## Reviews

**Purpose:** Social proof and quality feedback.

**Features:**

- Star rating (1–5) with optional title and text
- Photo uploads (up to 5 images per review)
- Verified purchase badge
- Admin moderation queue (approve/reject)
- Helpful vote count (Phase 2)
- Average rating aggregation on product cards

## Wishlist

**Purpose:** Save products for future purchase.

**Features:**

- Add/remove products and variants
- Wishlist page with move-to-cart action
- Share wishlist (Phase 2)
- Price drop notifications (Phase 2)

## Coupons

**Purpose:** Promotional discount management.

**Coupon types:**

- Percentage discount (e.g., 10% off)
- Fixed amount discount (e.g., TZS 5,000 off)
- Free shipping

**Rules:**

- Minimum order value
- Usage limit (global and per user)
- Valid date range
- Applicable categories/products restriction
- Single coupon per order

## Notifications

**Purpose:** Keep customers informed across channels.

**Channels:**

- In-app notifications (bell icon, notification center)
- Email (transactional: order confirmation, shipping updates, quotation ready)
- SMS (order status, payment confirmation) — Phase 2
- Push notifications (mobile app) — Phase 3

**Notification types:**

- Order confirmed
- Payment received
- Order shipped / in transit / delivered
- Quotation ready (China Order)
- Review reminder
- Promotional (opt-in only)

## Order Tracking

**Purpose:** Real-time visibility into shipment progress.

**Features:**

- Tracking page accessible via order detail or public tracking link (order number + email)
- Timeline UI with milestones: confirmed → processing → shipped → in transit → customs → out for delivery → delivered
- Carrier name and tracking number
- Estimated delivery date
- SMS/email updates at key milestones

---

# China Order Module

**Purpose:** Enable customers to request products not in the catalog by submitting supplier links or reference images, receiving a formal quotation, and converting to a paid import order.

## Upload Image

**Flow:**

1. Customer uploads one or more reference images (product photo, screenshot, design)
2. System stores images in object storage; creates `china_order_attachments` records
3. Optional: AI image search matches against supplier catalogs (see AI Features)
4. Customer adds quantity, size/color notes, and delivery preferences
5. Request enters admin quotation queue

**Constraints:**

- Accepted formats: JPEG, PNG, WebP
- Max file size: 10 MB per image
- Max images per request: 10

## Paste Alibaba Link

**Flow:**

1. Customer pastes Alibaba.com product URL
2. Backend parses URL, extracts product ID and metadata (title, images, MOQ, unit price in CNY where available)
3. Metadata stored in `china_order_source_links` with `platform = alibaba`
4. Customer confirms/edits item details and submits for quotation

**Supported URL patterns:** `alibaba.com/product-detail/*`, `m.alibaba.com/*`

## Paste 1688 Link

**Flow:** Same as Alibaba with `platform = 1688`.

**Supported URL patterns:** `detail.1688.com/offer/*`, `m.1688.com/*`

**Note:** 1688 prices are typically wholesale/factory pricing in CNY; quotation must include currency conversion, sourcing fee, and shipping.

## Paste Taobao Link

**Flow:** Same as Alibaba with `platform = taobao`.

**Supported URL patterns:** `item.taobao.com/item.htm*`, `m.taobao.com/*`

## Quotation System

**Purpose:** Admin-generated, customer-facing price proposal for China order requests.

**Workflow:**

```
Customer submits request → Admin reviews → Admin creates quotation → Customer notified
→ Customer accepts/rejects → Accepted quotation converts to order → Payment → Fulfillment
```

**Quotation components:**

| Line Item | Description |
|-----------|-------------|
| Product cost | Supplier unit price × quantity (CNY → TZS conversion) |
| Sourcing fee | Platform service fee (% or flat) |
| Domestic China shipping | Supplier to warehouse/forwarder |
| International shipping | Air or sea freight to Tanzania |
| Customs & duties | Estimated import taxes |
| **Total** | Final price in TZS |

**Quotation statuses:** `draft`, `sent`, `accepted`, `rejected`, `expired`

**Rules:**

- Quotations valid for 7 days by default (configurable)
- Customer must accept quotation before payment
- Admin can revise and resend quotations
- All status changes logged in `china_order_status_history`

---

# AI Features

## AI Product Search

**Purpose:** Natural language and semantic product discovery across the catalog.

**Capabilities:**

- Text query → semantic match against product titles, descriptions, and attributes
- Swahili and English query support (Phase 2)
- Search suggestions and autocomplete
- Search analytics via `ai_search_logs`

**Implementation (planned):**

- Product embeddings stored in `product_embeddings`
- Vector similarity search (MySQL vector extensions or dedicated vector DB)
- Fallback to full-text MySQL search when AI unavailable

## Image Search

**Purpose:** Find products by uploading a reference photo.

**Capabilities:**

- Customer uploads image → system generates embedding → matches against `product_embeddings`
- Results ranked by visual similarity with confidence score
- Integrated into China Order flow (identify supplier product from photo)
- Session tracking in `ai_image_search_sessions`

**Constraints:**

- Same image upload limits as China Order module
- Results displayed with "similar products" and "request quotation" CTA

## Recommendations

**Purpose:** Personalized product suggestions to increase engagement and AOV.

**Types:**

| Type | Trigger | Placement |
|------|---------|-----------|
| Homepage featured | Trending + seasonal | Homepage grid |
| Related products | Product viewed | Product detail page |
| Frequently bought together | Cart contents | Cart and checkout |
| Personalized for you | User browsing/purchase history | Homepage, account dashboard |
| Recently viewed | Session history | Homepage sidebar |

**Implementation:**

- Precomputed sets in `ai_recommendations` refreshed via scheduled jobs
- Cold-start fallback: category bestsellers and featured products
- A/B testing framework (Phase 3)

---

# UI Rules

## Layout

- **Max content width:** `max-w-7xl` (1280px) centered with responsive horizontal padding (`px-4 sm:px-6 lg:px-8`)
- **Section spacing:** Consistent vertical rhythm — `py-16` to `py-24` between major sections
- **Grid:** 12-column responsive grid; product grids: 2 cols mobile, 3 tablet, 4 desktop

## Components

- **Buttons:** Primary = gold filled rounded-full; Secondary = bordered zinc on dark/light surfaces
- **Cards:** Rounded corners (`rounded-2xl`), subtle borders (`border-zinc-200`), hover elevation on product cards
- **Inputs:** Rounded-full search inputs; standard rounded-lg for form fields; gold focus ring (`focus:ring-[#c9a227]/20`)
- **Badges:** Small uppercase pills for product labels (Hot Deal, New, Flash Sale)
- **Icons:** Consistent 16–24px SVG icons; no mixed icon libraries

## Responsive Breakpoints

Follow Tailwind defaults: `sm` (640px), `md` (768px), `lg` (1024px), `xl` (1280px), `2xl` (1536px).

Mobile-first: design for mobile, enhance for larger screens.

## Accessibility

- WCAG 2.1 AA compliance target
- All interactive elements keyboard accessible
- `sr-only` labels on icon-only buttons
- Color contrast ratio ≥ 4.5:1 for body text
- Focus indicators visible on all focusable elements
- `aria-expanded`, `aria-label` on toggles and menus

## Motion

- Subtle transitions on hover (`transition`, `hover:scale-105` on cards)
- `animate-pulse` for live indicators only
- Respect `prefers-reduced-motion` — disable animations when set

## Empty & Loading States

- Skeleton loaders for product grids and order lists
- Friendly empty states with illustration and CTA (e.g., "Your cart is empty — Start Shopping")
- Error states with retry action and support link

---

# Coding Standards

## General

- Write self-documenting code; comments only for non-obvious business logic
- No secrets in source control — use environment variables
- Keep diffs focused; one concern per pull request
- All new features require corresponding API documentation updates

## Frontend (TypeScript / React / Next.js)

| Rule | Standard |
|------|----------|
| Language | TypeScript strict mode |
| Components | Functional components only; named exports for components |
| File naming | PascalCase for components (`ProductCard.tsx`); camelCase for utilities |
| Imports | Absolute imports via `@/` alias |
| Styling | Tailwind utility classes; no inline styles except dynamic values |
| Props | Explicit TypeScript interfaces; no `any` |
| Client boundary | `"use client"` only when hooks or browser APIs are required |
| Data fetching | Server Components for read-heavy pages; no fetch in Client Components unless necessary |
| Formatting | Prettier with prettier-plugin-tailwindcss |

## Backend (PHP / Laravel)

| Rule | Standard |
|------|----------|
| Language | PHP 8.4+ with strict types (`declare(strict_types=1)`) |
| Style | Laravel Pint (PSR-12) |
| Controllers | Single responsibility; max ~10 lines of logic per action |
| Validation | Form Request classes; never validate in controllers directly |
| Responses | API Resource classes for all JSON output |
| Models | Eloquent with defined `$fillable`, `$casts`, and relationships |
| Migrations | Reversible; descriptive names; foreign keys with indexes |
| Tests | Feature tests for all API endpoints; unit tests for services |
| Naming | PSR-4 autoloading; descriptive class and method names |

## Git Conventions

| Item | Convention |
|------|------------|
| Branch naming | `feature/`, `fix/`, `chore/`, `docs/` prefixes |
| Commit messages | Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`) |
| PR size | Prefer < 400 lines changed; split large features |
| Reviews | At least one approval before merge to `main` |

---

# Security Standards

## Application Security

- **HTTPS only** in production; HSTS enabled
- **CORS:** Restrict to `FRONTEND_URL` origin
- **CSRF:** Sanctum CSRF cookie flow for SPA
- **XSS:** React auto-escaping; sanitize rich text if CMS added
- **SQL injection:** Eloquent parameterized queries only; no raw SQL without bindings
- **Mass assignment:** `$fillable` / `$guarded` on all models
- **File uploads:** Validate MIME type, extension, and size; store outside web root
- **Rate limiting:** Applied to all public and auth endpoints (see API Architecture)

## Authentication & Authorization

- Bcrypt password hashing (cost factor 12)
- Token expiration: access token 24h; refresh token 30 days
- Role-based access control via Policies
- Admin routes require `role:admin` middleware
- Resource ownership checks (users access only their orders, cart, wishlist)

## Data Protection

- PII encrypted at rest where supported (MySQL TDE or application-level for sensitive fields)
- Payment data never stored locally — tokenized via payment gateway
- Audit logging for admin actions on orders, payments, and user accounts
- GDPR-inspired data export and deletion endpoints (Phase 2)

## Infrastructure Security

- Docker images scanned in CI
- Secrets managed via environment variables / secret manager (production)
- Database credentials rotated quarterly
- Regular dependency updates (`composer audit`, `npm audit`)
- `APP_DEBUG=false` in production

## Compliance

- PCI DSS considerations for card payments (via compliant gateway — no raw card storage)
- Tanzania Data Protection Act awareness for user data handling

---

# Performance Standards

## Frontend Targets

| Metric | Target |
|--------|--------|
| Largest Contentful Paint (LCP) | < 2.5s |
| First Input Delay (FID) | < 100ms |
| Cumulative Layout Shift (CLS) | < 0.1 |
| Time to First Byte (TTFB) | < 600ms |
| Lighthouse Performance score | ≥ 90 |

**Techniques:**

- Next.js Image optimization (`next/image`) with WebP/AVIF
- Server Components to minimize client JavaScript bundle
- Code splitting per route
- Static generation for category and product pages (ISR)
- Font subsetting via `next/font`
- Lazy load below-fold images and components

## Backend Targets

| Metric | Target |
|--------|--------|
| API response time (p95) | < 300ms for catalog reads |
| API response time (p95) | < 800ms for checkout/order creation |
| Database query time (p95) | < 50ms |
| Queue job processing | < 30s for notification jobs |

**Techniques:**

- Eager loading to prevent N+1 queries
- Redis caching for catalog, categories, and settings
- Database indexes on foreign keys, slugs, and filter columns
- Pagination on all list endpoints
- CDN for static assets and product images
- Horizontal scaling via stateless API containers

## Monitoring

- Application performance monitoring (APM) — planned
- Error tracking (Sentry or equivalent) — planned
- Uptime monitoring on `/api/v1/health` and frontend
- Database slow query logging

---

# SEO Standards

## Technical SEO

- Server-side rendering for all public catalog pages
- Semantic HTML (`<header>`, `<main>`, `<nav>`, `<article>`, `<footer>`)
- Canonical URLs on all pages
- XML sitemap auto-generated (`/sitemap.xml`)
- `robots.txt` with appropriate allow/disallow rules
- Structured data (JSON-LD): Organization, Product, BreadcrumbList, FAQPage
- Open Graph and Twitter Card meta tags on all public pages
- `hreflang` tags when Swahili localization launches

## URL Structure

| Page | URL Pattern |
|------|-------------|
| Homepage | `/` |
| Category | `/categories/{slug}` |
| Product | `/products/{slug}` |
| Static page | `/pages/{slug}` |
| China Order | `/china-order` |

**Rules:** Lowercase, hyphen-separated slugs; no query parameters for primary content; 301 redirects for slug changes.

## Content SEO

- Unique `<title>` and `<meta description>` per page (max 60 / 160 characters)
- H1 per page (one only); logical heading hierarchy (H1 → H2 → H3)
- Alt text on all product and category images
- Internal linking between categories, products, and static pages
- Blog/content marketing section (Phase 2) for long-tail keywords

## Performance SEO

- Core Web Vitals targets (see Performance Standards)
- Mobile-friendly responsive design (Google mobile-first indexing)
- No render-blocking resources above the fold

---

# Future Roadmap

## Phase 1 — Foundation (Current → Q3 2026)

- [x] Monorepo scaffolding (Next.js 15 + Laravel 12 + Docker)
- [x] Homepage UI with brand identity
- [x] API health endpoint
- [ ] Database migrations for all planned tables
- [ ] Authentication (register, login, Sanctum)
- [ ] Catalog CRUD (admin) and public product browsing
- [ ] Cart and checkout (M-Pesa integration)
- [ ] Order management and tracking
- [ ] China Order module (link paste + image upload + quotation workflow)

## Phase 2 — Growth (Q4 2026)

- [ ] Admin dashboard UI
- [ ] Reviews and wishlist
- [ ] Coupons and promotions engine
- [ ] Email and SMS notifications
- [ ] AI product search (semantic)
- [ ] Swahili localization
- [ ] Additional payment methods (cards, Tigo Pesa, Airtel Money)
- [ ] SEO sitemap and structured data automation
- [ ] Analytics dashboard for admin

## Phase 3 — Scale (2027)

- [ ] AI image search and visual recommendations
- [ ] Mobile app (React Native or Flutter)
- [ ] Multi-factor authentication (SMS OTP)
- [ ] Bulk/B2B procurement portal
- [ ] Supplier portal for direct inventory sync
- [ ] East Africa market expansion (Kenya, Uganda)
- [ ] Loyalty program and referral system
- [ ] Live chat support integration
- [ ] Advanced A/B testing and personalization engine

## Phase 4 — Platform (2027+)

- [ ] Marketplace model (third-party sellers)
- [ ] Real-time freight cost calculator with carrier API integration
- [ ] Automated customs documentation
- [ ] White-label China Order API for partners
- [ ] Machine learning demand forecasting for inventory

---

## Document Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2026-06-25 | Engineering | Initial master specification |

---

*This document is proprietary to CHINA ORDER TZ. All rights reserved.*
