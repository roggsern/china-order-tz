/**
 * Web OrderDetailsContent / CustomerReturnRequestContent CTA contract.
 * Backend ReturnEligibilityService remains authoritative on submit.
 */
export function shouldOfferReturnRequest(status: string | null | undefined): boolean {
  const normalized = status?.trim().toLowerCase() ?? '';
  return normalized === 'delivered' || normalized === 'completed';
}

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function isReturnableOrderItemId(id: string | null | undefined): boolean {
  return typeof id === 'string' && UUID_RE.test(id);
}

export function clampReturnQuantity(quantity: number, max: number): number {
  if (!Number.isFinite(quantity) || !Number.isFinite(max)) return 1;
  return Math.max(1, Math.min(Math.trunc(max), Math.trunc(quantity)));
}

export function isSupportedReturnReason(reason: string | null | undefined): boolean {
  const trimmed = reason?.trim() ?? '';
  return trimmed.length > 0 && trimmed.length <= 255;
}
