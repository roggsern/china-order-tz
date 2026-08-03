import type { PosCartLine, PosCatalogItem } from "@/lib/api/admin-pos";
import { posCatalogItemRowKey } from "@/lib/admin/pos/pos-catalog-image";

export function posCartLineTotal(unitPrice: string, quantity: number): string {
  return (Number(unitPrice) * quantity).toFixed(2);
}

export function addPosCartLine(cart: PosCartLine[], item: PosCatalogItem): PosCartLine[] {
  const lineKey = posCatalogItemRowKey(item);
  const existing = cart.find((line) => posCatalogItemRowKey(line) === lineKey);

  if (existing) {
    const quantity = Math.min(existing.quantity + 1, item.available_stock);

    return cart.map((line) =>
      posCatalogItemRowKey(line) === lineKey
        ? { ...line, quantity, line_total: posCartLineTotal(line.unit_price, quantity) }
        : line,
    );
  }

  return [
    ...cart,
    {
      ...item,
      quantity: 1,
      line_total: posCartLineTotal(item.unit_price, 1),
    },
  ];
}
