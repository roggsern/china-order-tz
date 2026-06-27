/** Routes where cart catalog re-validation must not run (post-checkout). */
export const POST_CHECKOUT_PATH_PREFIXES = [
  "/order-success",
  "/track-order",
  "/checkout/payment/confirm",
  "/checkout/payment/processing",
] as const;

export function isPostCheckoutPath(pathname: string): boolean {
  return POST_CHECKOUT_PATH_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

export function isAdminPath(pathname: string): boolean {
  return pathname.startsWith("/admin");
}

export function isCheckoutPath(pathname: string): boolean {
  return pathname.startsWith("/checkout");
}
