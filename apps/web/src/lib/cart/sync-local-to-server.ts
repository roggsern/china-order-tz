import type { CartLineItem } from "@/lib/types/cart";
import type { ServerCartItem } from "@/lib/api/customer-cart";

function normalizeVariantId(value: string | null | undefined): string {
  return value?.trim() ?? "";
}

/** Avoid duplicating lines when local cart is pushed to an already-populated server cart. */
export function isLocalCartItemAlreadyOnServer(
  localItem: CartLineItem,
  serverItems: ServerCartItem[],
): boolean {
  const localProductId = localItem.catalogProductId?.trim();
  if (!localProductId) {
    return false;
  }

  const localVariantId = normalizeVariantId(localItem.configurationId);

  return serverItems.some((serverItem) => {
    const serverProductId = serverItem.product_id?.trim();
    if (!serverProductId || serverProductId !== localProductId) {
      return false;
    }

    const serverVariantId = normalizeVariantId(serverItem.product_variant_id);

    return serverVariantId === localVariantId;
  });
}

export function filterLocalItemsForServerSync(
  localItems: CartLineItem[],
  serverItems: ServerCartItem[],
): CartLineItem[] {
  if (serverItems.length === 0) {
    return localItems;
  }

  return localItems.filter((item) => !isLocalCartItemAlreadyOnServer(item, serverItems));
}
