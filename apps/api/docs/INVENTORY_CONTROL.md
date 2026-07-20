# Inventory Control & Stock Operations (TASK 042)

Professional inventory control for CHINA ORDER TZ retail stores. Stores are separate business units — stock is never assumed transferable across unrelated brands (Zion Mode, Peachy Lingerie, Tzur Jewelry, Rovi Beauty).

## Source of truth

`VariantInventory` + `InventoryLocation` remain authoritative. This module adds:

- Append-only `inventory_stock_movements`
- Stock count sessions / lines
- Damaged & inspection quantity buckets on `VariantInventory`
- Store-scoped receiving metadata on `receiving_records`

## Movement types

`receive` · `sale` · `return` · `adjustment` · `damage` · `correction`

Every sellable change goes through `InventoryControlEngine` and writes a ledger row (quantity before / change / after, location, actor, reason, reference).

## Receiving

`ReceivingEngine` still owns PO quantity checks (cannot receive above outstanding). Intake uses store inventory locations when `store_id` / product store is known; otherwise legacy `MAIN` warehouse.

## Damaged stock

`damaged` units are excluded from `available()` / POS sellable stock. Mark via adjustment `kind=damage` or POS damaged returns (`recordDamagedIntake`).

## Stock counts

Create → count lines → submit → approve (reason required for variances). Approval posts adjustment movements.

## Admin

- UI: `/admin/inventory`
- API: `/admin/inventory`, `/stock`, `/movements`, `/adjustments`, `/counts`, `/valuation`, `/low-stock`

## Permissions

Scoped by `ActiveStoreContext` — cashiers see assigned stores only; super admin sees all.
