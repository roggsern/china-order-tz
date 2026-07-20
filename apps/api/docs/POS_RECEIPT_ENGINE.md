# POS Receipt Engine (TASK 038)

Receipts are a **presentation layer** over completed Orders. The Order Engine remains the source of truth — reprint never creates another order or inventory movement.

## Numbering

Format: `{STORE_CODE}-{YEAR}-{SEQ:6}`

Example: `ZION-2026-000001`

Store-scoped yearly sequences (locked) — unique, searchable, audit-friendly.

## Layouts

| Layout | API |
|--------|-----|
| 80mm thermal (primary) | `layout=thermal_80` |
| A4 printable | `layout=a4` |
| PDF download | `GET .../pdf` |

Future delivery channels (email / WhatsApp / SMS) reuse the same snapshot + `delivery_channels` flags.

## Store branding / settings

Configure via `stores.settings.receipt` (no code changes):

- `address`, `phone`, `tax_number`
- `footer_message`, `thank_you_message`
- `return_policy`, `exchange_policy`
- `website`, `social_media`

Also uses `logo_path` and `theme_color` from the Store.

## APIs

| Method | Path |
|--------|------|
| GET | `/admin/pos/receipts` |
| GET | `/admin/pos/receipts/{receipt}` |
| GET | `/admin/pos/receipts/{receipt}/preview` |
| POST | `/admin/pos/receipts/{receipt}/print` |
| POST | `/admin/pos/receipts/{receipt}/reprint` |
| GET | `/admin/pos/receipts/{receipt}/pdf` |
| GET | `/admin/pos/orders/{order}/receipt` |

## Audit events

- `pos_receipt_generated`
- `pos_receipt_printed`
- `pos_receipt_reprinted`

## QR stub

`qr_payload` stores `{ type, receipt_id, receipt_number, order_id, payload, url: null }` for future verification / deep links — no verification service yet.

## UI

- `/admin/pos` — print after sale
- `/admin/pos/receipts` — manager search, preview, reprint, PDF
