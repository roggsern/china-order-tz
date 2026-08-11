/**
 * Shared customer-facing money display.
 * Never recalculates — formats server amounts only.
 */
export function formatCustomerMoney(
  value: string | number | null | undefined,
  currency = 'TZS',
): string {
  if (value == null || value === '') return '—';

  const raw = typeof value === 'number' ? value : Number(String(value).replace(/,/g, '').trim());
  if (!Number.isFinite(raw)) {
    return `${currency} ${String(value)}`;
  }

  const isWhole = Math.abs(raw - Math.round(raw)) < 0.001;
  const formatted = new Intl.NumberFormat('en-TZ', {
    minimumFractionDigits: isWhole ? 0 : 2,
    maximumFractionDigits: isWhole ? 0 : 2,
  }).format(raw);

  return `${currency} ${formatted}`;
}
