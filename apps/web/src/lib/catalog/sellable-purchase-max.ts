/**
 * Purchase quantity ceiling is available sellable stock — never an invented cap.
 * Unknown / non-finite / non-positive stock is 0 (fail closed, not unlimited).
 */
export function sellablePurchaseMax(stock: number | null | undefined): number {
  if (typeof stock !== "number" || !Number.isFinite(stock) || stock <= 0) {
    return 0;
  }

  return Math.floor(stock);
}

export function canIncreasePurchaseQuantity(quantity: number, max: number): boolean {
  return quantity < max;
}

export function nextPurchaseQuantity(quantity: number, max: number, min = 1): number {
  return Math.min(max, Math.max(min, quantity + 1));
}
