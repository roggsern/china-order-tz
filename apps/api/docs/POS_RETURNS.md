# POS Returns, Exchanges & Refunds (TASK 039)

Returns reference existing Orders. Refunds use `refund_transactions`. Inventory uses store `VariantInventory`. Profit uses `ProfitEngine::reverseForReturn`.

## Flow

Receipt/Order lookup → validate POS eligibility → select items → reason → refund or exchange → inventory → profit → return receipt snapshot → audit.

## Return reasons

Configurable table `return_reasons` (seeded). Admin can extend without code changes.

## Inventory dispositions

| Disposition | Behavior |
|-------------|----------|
| `sellable` | Restock store location `on_hand` |
| `damaged` | No restock + audit |
| `inspection` | No restock (future workflow) |

## APIs

| Method | Path |
|--------|------|
| GET | `/admin/pos/return-reasons` |
| GET | `/admin/pos/returns/search` |
| GET | `/admin/pos/orders/{order}/return-preview` |
| POST | `/admin/pos/returns` |
| GET | `/admin/pos/returns` |
| GET | `/admin/pos/returns/{id}` |
| GET | `/admin/pos/orders/{order}/returns` |
| GET | `/admin/pos/returns/report` |

## UI

`/admin/pos/returns` — search, wizard, history.

## Numbering

`RET-{STORE}-{YEAR}-{SEQ}` e.g. `RET-ZION-2026-000001`
