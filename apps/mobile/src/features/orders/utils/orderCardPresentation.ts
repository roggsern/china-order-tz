import type { OrderDetailItem, OrderListItem } from '../models/types';

/**
 * Display model for order list cards — uses only server-mapped preview fields.
 * List API exposes a single primary image; multi-item honesty is via "+N more".
 */
export type OrderListCardPresentation = {
  imageUrl: string | null;
  productName: string | null;
  extraItems: number;
  isMultiItem: boolean;
  quantity: number | null;
  orderNumber: string | null;
  statusLabel: string;
  createdAt: string | null;
  grandTotal: string | number | null;
  currency: string;
  journeyLabel: string | null;
  paymentStatus: string | null;
};

export function buildOrderListCardPresentation(
  order: OrderListItem,
): OrderListCardPresentation {
  const preview = order.preview;
  const primary = preview?.primaryItem ?? null;
  const extraItems = preview?.extraItems ?? 0;
  const itemCount = preview?.itemCount ?? (primary ? 1 + extraItems : 0);

  return {
    imageUrl: primary?.imageUrl ?? null,
    productName: primary?.name ?? null,
    extraItems,
    isMultiItem: itemCount > 1 || extraItems > 0,
    quantity: primary?.quantity ?? preview?.totalQuantity ?? null,
    orderNumber: order.orderNumber ?? order.id,
    statusLabel: order.statusLabel ?? order.status ?? 'Status unavailable',
    createdAt: order.createdAt,
    grandTotal: order.grandTotal,
    currency: order.currency ?? 'TZS',
    journeyLabel: order.journeyLabel,
    paymentStatus: order.paymentStatus,
  };
}

/** Title line: primary product name with honest multi-item suffix. */
export function formatOrderListProductTitle(
  presentation: OrderListCardPresentation,
): string | null {
  if (!presentation.productName) return null;
  if (presentation.extraItems > 0) {
    return `${presentation.productName} +${presentation.extraItems} more`;
  }
  return presentation.productName;
}

/**
 * Item thumbnails for tracking / multi-item honesty from order detail items.
 * Does not invent images — only passes through mapped `imageUrl` values.
 */
export function collectOrderItemImageUrls(
  items: OrderDetailItem[] | null | undefined,
): string[] {
  if (!items?.length) return [];
  const urls: string[] = [];
  for (const item of items) {
    const url = item.imageUrl?.trim();
    if (url) urls.push(url);
  }
  return urls;
}
