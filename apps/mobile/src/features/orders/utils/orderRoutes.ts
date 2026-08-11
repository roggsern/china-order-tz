/**
 * In-app routes for the orders experience.
 */
export function buildOrdersListHref(): string {
  return '/(app)/(tabs)/orders';
}

export function buildOrderDetailHref(orderId: string): string {
  return `/(app)/orders/${encodeURIComponent(orderId)}`;
}

export function buildOrderTrackingHref(orderId: string): string {
  return `/(app)/orders/${encodeURIComponent(orderId)}/tracking`;
}

/**
 * After server-confirmed payment, navigate using the order id from the payment response.
 * Falls back to the orders list when no id is available — never invents order state.
 */
export function buildPostPaymentOrdersHref(orderId: string | null | undefined): string {
  const id = typeof orderId === 'string' ? orderId.trim() : '';
  if (id) {
    return buildOrderDetailHref(id);
  }
  return buildOrdersListHref();
}
