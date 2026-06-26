const ORDER_SEQUENCE_KEY_PREFIX = "china-order-tz-order-seq";

function getSequenceKey(year: number): string {
  return `${ORDER_SEQUENCE_KEY_PREFIX}-${year}`;
}

export function generateOrderNumber(): string {
  const year = new Date().getFullYear();

  if (typeof window === "undefined") {
    return `TZ${year}000001`;
  }

  const key = getSequenceKey(year);
  const current = Number.parseInt(window.localStorage.getItem(key) ?? "0", 10);
  const next = current + 1;
  window.localStorage.setItem(key, String(next));

  return `TZ${year}${String(next).padStart(6, "0")}`;
}
