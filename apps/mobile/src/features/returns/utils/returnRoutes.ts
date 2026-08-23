export function buildReturnsListHref(): string {
  return '/(app)/account/returns';
}

export function buildReturnDetailHref(returnId: string): string {
  return `/(app)/account/returns/${encodeURIComponent(returnId)}`;
}

export function buildOrderReturnHref(orderId: string): string {
  return `/(app)/orders/${encodeURIComponent(orderId)}/return`;
}
