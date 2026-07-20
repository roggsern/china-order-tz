# TASK 040A — Retail Smoke Validation Report

**Date:** 2026-07-20  
**Scope:** Validate retail engines together. No new product features.

## Stabilization applied (glue only)

| Fix | Why |
|-----|-----|
| `PosSaleService` fires `CommerceOrderCreated` + `PaymentConfirmed` and calls `ProfitEngine::calculateForOrder` | POS sales were not wiring CRM timeline / cost / profit engines |
| `PosReturnService::nextReturnNumber` casts Stringable → int | Second+ return numbers crashed (`Stringable` not castable to int) |

## Automated coverage

- `tests/Feature/Pos/RetailSmokeLifecycleTest` — full ZION MODE lifecycle + permission isolation
- Existing Pos / Returns / Session / Receipt / Analytics suites

---

## 1. Passed scenarios

| Step | Scenario | Result |
|------|----------|--------|
| 1 | Open POS session (100,000 float), status OPEN, audit | PASS |
| 2 | Catalog store isolation (ZION only; hide TZUR/ROVI/CHINA_IMPORT) | PASS |
| 2 | Variant price + store inventory location on quote/sale | PASS |
| 3A | Walk-in sale (`user_id` null) | PASS |
| 3B | CRM customer attached + timeline (order/payment) + metrics | PASS |
| 4 | Promotion `SMOKE10` discount on quote + order + usage row | PASS |
| 5 | CASH (amount/change), MPESA_LIPA manual, NMB_BANK manual | PASS |
| 6 | Receipt branding/number/payment; preview, print, PDF, reprint | PASS |
| 6 | Reprint does not duplicate order/payment/receipt | PASS |
| 7 | Inventory decrement on store location (not MAIN warehouse) | PASS |
| 8 | Cost snapshot + profit record after paid sale (post-stabilization) | PASS |
| 9 | CRM purchase timeline events | PASS |
| 10 | Return linked to order; qty validation; sellable restock; damaged no restock | PASS |
| 11 | Cash / MPESA / Bank refunds; refund audit; profit reversal | PASS |
| 12 | Session close with live expected cash; balanced variance; close audit | PASS |
| 13 | Analytics sales/profit/returns/payments/inventory/customers/sessions/stores | PASS |
| Sec | Store cashier / master assigned-only / super admin all stores | PASS |
| Data | No duplicate orders/payments/receipts for lifecycle | PASS |

## 2. Failed scenarios

None remaining after stabilization.

*(Previously failing before glue fixes: profit/CRM on POS sale; multi-return numbering crash.)*

## 3. Data inconsistencies found

| Item | Status |
|------|--------|
| Duplicate orders/payments/receipts in smoke path | None found |
| Return number sequence after first return | Fixed (Stringable cast) |
| Session `expected_cash` column may be stale until dashboard/close recalculation | Use live summary for close (documented; close path recalculates) |

## 4. Performance issues

| Observation | Severity | Note |
|-------------|----------|------|
| Full lifecycle feature test ~13–16s (SQLite RefreshDatabase) | Low | Acceptable for integration smoke |
| Analytics aggregations load inventory rows in PHP for velocity | Low | OK for current store scale; watch if SKU counts grow large |
| No N+1 failures observed in smoke path | — | — |

## 5. Recommended fixes before Loyalty / Growth

1. **Persist session cash totals after each sale/refund** — keep `cash_sales` / `cash_refunds` / `expected_cash` columns in sync so close UI never depends on stale columns.
2. **CRM metrics assertion surface** — expose a single admin “customer retail summary” (orders/spend/last purchase) already computed by `CustomerMetricsService` for POS managers.
3. **Idempotent POS sale keys** — optional client `idempotency_key` to harden against double-submit (not a bug today; growth risk).
4. **Return number generation** — consider DB sequence / unique constraint retry (cast fix is sufficient for now).
5. **Analytics caching** — short TTL cache for executive dashboard when store count grows.
6. **Do not add Loyalty until** profit + CRM hooks remain covered by `RetailSmokeLifecycleTest` in CI.

## Suite result

`Pos|RetailAnalytics|RetailSmoke` → **45 passed** (409 assertions)

## Test command

```bash
docker compose exec -T api php artisan test --filter="Pos|RetailAnalytics|RetailSmoke"
```
