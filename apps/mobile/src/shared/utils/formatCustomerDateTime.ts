/**
 * Format server ISO timestamps for customers.
 * Falls back to original string when unparseable.
 */
export function formatCustomerDateTime(
  value: string | null | undefined,
): string {
  if (typeof value !== 'string' || value.trim() === '') return '—';
  const parsed = Date.parse(value);
  if (!Number.isFinite(parsed)) return value;
  return new Date(parsed).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
