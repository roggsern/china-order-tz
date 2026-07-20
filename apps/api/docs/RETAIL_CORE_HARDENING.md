# Retail Core Hardening (TASK 040B)

Production readiness for the Retail Core. **No new business features.**

## Architecture summary

```
POS Client
  → PosSaleService / PosReturnService / PosSessionService
  → Order · Payment · Inventory · Profit · CRM · Receipt engines
  → PosSessionCashService (live running totals)
  → RetailAnalyticsEngine (read-only + short TTL cache)
```

Retail remains a thin orchestration layer over existing commerce engines.

## Production checklist

- [x] Persist session running totals after sale/refund
- [x] POS sale idempotency (`idempotency_key`)
- [x] Sale / return / close inside `DB::transaction`
- [x] Domain validation errors (`PosErrors`)
- [x] Operational info logs (session/sale/return/refund/reprint/export)
- [x] Analytics short TTL cache (dashboard + inventory)
- [x] Hot-path indexes (session, store+date, refunds, payments)
- [x] CI retail smoke gate
- [x] Hardening + smoke regression tests

## Idempotency strategy

| Item | Detail |
|------|--------|
| Field | Optional `idempotency_key` on `POST /admin/pos/sales` (max 128) |
| Scope | Unique per `(admin_id, idempotency_key)` |
| Storage | `pos_sale_idempotency_keys` → `order_id` |
| Replay | Returns original order/payment/receipt; HTTP **200** + `idempotent_replay: true` |
| Race | Unique constraint + catch → replay |
| Guarantees | No duplicate order, payment, receipt, inventory decrement, or profit row |

Clients should send a stable UUID per checkout attempt (cart submit).

## Caching strategy

| Endpoint | Cache | TTL |
|----------|-------|-----|
| Analytics dashboard | `Cache::remember` | 45s |
| Analytics inventory | `Cache::remember` | 45s |

Keys are filter-scoped (`AnalyticsFilter::cacheKey`). No permanent analytics tables. Invalidation is TTL expiry only (simple).

## Session totals

After every successful **sale** and **return/refund**, `PosSessionCashService::persistRunningTotals()` writes:

- `cash_sales`
- `cash_refunds`
- `expected_cash` (= float − refunds + cash sales)
- `transaction_count`
- `payment_breakdown`

Figures are always derived from Order / Payment / Refund engines, then persisted for open sessions. Close still reconciles via `snapshotForClose()`.

## Domain errors

`App\Support\Pos\PosErrors` standardizes 422 messages:

- Session Closed
- Insufficient Inventory
- Return Quantity Exceeded
- Receipt Not Found
- Store Access Denied

## CI gate

Monorepo workflow: `.github/workflows/retail-smoke.yml`

```bash
php artisan test --filter="Pos|RetailAnalytics|RetailSmoke"
```

**Merge rule:** PRs touching POS, Orders, Payments, Inventory, Profit, CRM, Analytics, or Returns must keep this gate green. Configure branch protection to require the `RetailSmokeLifecycle gate` check.

Also documented in `apps/api/.github/workflows/tests.yml` (`retail-smoke-gate` job).

## Operational recommendations

1. Send `idempotency_key` from the POS UI on every sale submit.
2. Monitor logs: `pos.sale_complete`, `pos.return_complete`, `pos.session_close`, `pos.profit_calculation_failed`.
3. Prefer Redis/array cache store in production for analytics TTL (not `database` unless necessary).
4. Keep `RetailSmokeLifecycleTest` as the release gate before Loyalty (TASK 041).

## Known future improvements

- Persist float adjustments as explicit cash movements
- Optional sale idempotency header (`Idempotency-Key`) in addition to body field
- Targeted cache bust on sale/return for the active store (still keep short TTL)
- Concurrent stress tests under load tooling
- DB-level return number sequence object (MySQL)

## Related docs

- `RETAIL_SMOKE_VALIDATION_040A.md`
- `RETAIL_ANALYTICS.md`
- `POS_RETURNS.md`
- `POS_SESSION_CASH_MANAGEMENT.md`
