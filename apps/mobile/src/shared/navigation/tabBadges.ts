import { isOrderPayableFromServer } from '@/src/features/orders/utils/isOrderPayable';

/**
 * Pure helpers for tab bar badge labels — no React / API imports.
 */

export function formatTabBadgeCount(count: number | null | undefined): string | undefined {
  if (count == null || !Number.isFinite(count) || count <= 0) {
    return undefined;
  }
  const whole = Math.floor(count);
  if (whole <= 0) return undefined;
  if (whole > 99) return '99+';
  return String(whole);
}

export function countPayableOrders(
  orders:
    | {
        status: string | null;
        canPay?: boolean | null;
        paymentStatus?: string | null;
      }[]
    | null
    | undefined,
): number {
  if (!orders?.length) return 0;
  let count = 0;
  for (const order of orders) {
    if (isOrderPayableFromServer(order)) {
      count += 1;
    }
  }
  return count;
}
