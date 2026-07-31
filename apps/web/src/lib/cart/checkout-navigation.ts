export const CHECKOUT_ROUTE = "/checkout" as const;

export function isCartPageContentVisible(isHydrated: boolean): boolean {
  return isHydrated;
}

export function isCheckoutPageContentVisible(
  isHydrated: boolean,
  wizardLoaded: boolean,
): boolean {
  return isHydrated && wizardLoaded;
}

export function canProceedToCheckout(isHydrated: boolean, itemCount: number): boolean {
  return isHydrated && itemCount > 0;
}

export function resolveCheckoutRoute(itemCount: number): typeof CHECKOUT_ROUTE | null {
  if (itemCount <= 0) {
    return null;
  }

  return CHECKOUT_ROUTE;
}
