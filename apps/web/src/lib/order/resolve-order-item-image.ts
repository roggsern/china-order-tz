import {
  getProductPrimaryImage,
  isPlaceholderImageUrl,
  PRODUCT_PLACEHOLDER_IMAGE,
  resolveImageUrl,
} from "@/lib/catalog/product-images";
import type { Product } from "@/lib/types/catalog";
import type { OrderLineItem } from "@/lib/types/order";

export type OrderItemImageInput = {
  snapshotUrl?: string | null;
  productPrimaryImageUrl?: string | null;
};

/** Resolve historical order item media for list + detail surfaces. */
export function resolveOrderItemImageUrl(input: OrderItemImageInput): string {
  const snapshot = input.snapshotUrl?.trim();
  if (snapshot) {
    const fromSnapshot = resolveImageUrl(snapshot);
    if (!isPlaceholderImageUrl(fromSnapshot)) {
      return fromSnapshot;
    }
  }

  const primary = input.productPrimaryImageUrl?.trim();
  if (primary) {
    const fromProduct = resolveImageUrl(primary);
    if (!isPlaceholderImageUrl(fromProduct)) {
      return fromProduct;
    }
  }

  return PRODUCT_PLACEHOLDER_IMAGE;
}

export function resolveOrderLineItemImage(item: OrderLineItem): string {
  return resolveOrderItemImageUrl({
    snapshotUrl: item.image.url,
    productPrimaryImageUrl: item.image.fallbackProductUrl,
  });
}

export function applyResolvedImageToOrderLineItem(item: OrderLineItem): OrderLineItem {
  const url = resolveOrderItemImageUrl({
    snapshotUrl: item.image.url,
    productPrimaryImageUrl: item.image.fallbackProductUrl,
  });

  return {
    ...item,
    image: {
      ...item.image,
      url,
    },
  };
}

export function findCatalogProductPrimaryUrl(
  productId: number | string,
  slug: string | undefined,
  products: Product[],
): string | undefined {
  const idStr = String(productId).trim();
  const slugStr = slug?.trim();

  const match = products.find((product) => {
    if (product.catalogProductId && product.catalogProductId === idStr) {
      return true;
    }

    if (String(product.id) === idStr) {
      return true;
    }

    return Boolean(slugStr && product.slug === slugStr);
  });

  if (!match) {
    return undefined;
  }

  const primary = getProductPrimaryImage(match);
  return primary.url?.trim() || primary.path?.trim() || undefined;
}

export function enrichOrderLineItemsWithCatalogImages(
  items: OrderLineItem[],
  products: Product[],
  snapshotSources: Array<{
    product_id?: number | string;
    product_slug_snapshot?: string | null;
    product_image_snapshot?: string | null;
    image_snapshot?: string | null;
  }>,
): OrderLineItem[] {
  return items.map((item, index) => {
    const source = snapshotSources[index];
    const hasSnapshot = Boolean(
      source?.product_image_snapshot?.trim() || source?.image_snapshot?.trim(),
    );

    if (hasSnapshot) {
      return item;
    }

    const productId = source?.product_id ?? item.productId;
    const slug = source?.product_slug_snapshot ?? item.slug;
    const fallbackProductUrl = findCatalogProductPrimaryUrl(productId, slug, products);

    if (!fallbackProductUrl) {
      return applyResolvedImageToOrderLineItem(item);
    }

    return applyResolvedImageToOrderLineItem({
      ...item,
      image: {
        ...item.image,
        fallbackProductUrl,
      },
    });
  });
}
