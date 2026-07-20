# POS Session & Cash Management (TASK 037)

POS remains a thin sales client. Session cash accountability is derived from the Order and Payment engines — no duplicate sale or payment tables.

## Rules

- Every POS sale requires an **OPEN** session (`pos_session_id` on the order).
- One open session per **cashier**.
- One open session per **terminal**.
- Opening float is **required** (may be `0.00`).
- Closing requires **actual cash count**.
- Expected cash = opening float + cash sales − cash refunds.
- Cash methods are detected via Payment Engine definitions (`config.handler = cash_with_change`), not hardcoded codes.
- Variance types: `balanced` | `over` | `short`.

## APIs

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/admin/pos/dashboard` | Cashier dashboard + live summary |
| GET | `/admin/pos/sessions` | Manager session list (store-scoped) |
| GET | `/admin/pos/sessions/current` | Current open session + summary |
| GET | `/admin/pos/sessions/{id}` | Session detail + summary |
| POST | `/admin/pos/sessions/open` | Open (`opening_float` required) |
| POST | `/admin/pos/sessions/close` | Close (`closing_cash` required) |
| PATCH | `/admin/pos/sessions/float` | Update opening float while open |

## Audit events

- `pos_session_opened`
- `pos_session_closed`
- `pos_variance_detected` (when not balanced)
- `pos_float_updated`

## Reporting

Report type `pos_sessions` via Reporting Platform (`ReportGenerator`).

## UI

- `/admin/pos` — cashier selling + session dashboard + close cash count
- `/admin/pos/sessions` — manager variance / totals view
