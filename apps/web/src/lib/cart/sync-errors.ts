import { CustomerCartApiError } from "@/lib/api/customer-cart";

export function isCustomerCartApiError(error: unknown): error is CustomerCartApiError {
  return error instanceof CustomerCartApiError;
}

/**
 * Transient/unavailable failures where local cart fallback is acceptable.
 */
export function isCustomerCartNetworkError(error: unknown): boolean {
  if (error instanceof TypeError) {
    return true;
  }

  if (isCustomerCartApiError(error)) {
    const status = error.statusCode;
    return status !== undefined && status >= 500;
  }

  return false;
}

/**
 * Stale/invalid Bearer rejected by Sanctum / ensure.user.
 */
export function isCustomerCartAuthError(error: unknown): boolean {
  return isCustomerCartApiError(error) && error.statusCode === 401;
}

/**
 * Validation/business failures from the cart API (422 mixed channel, stock, etc.).
 * Auth failures are handled separately as recoverable guest fallback.
 */
export function isCustomerCartBusinessError(error: unknown): boolean {
  return (
    isCustomerCartApiError(error) &&
    !isCustomerCartNetworkError(error) &&
    !isCustomerCartAuthError(error)
  );
}

export function shouldFallbackToLocalCartOnError(error: unknown): boolean {
  return isCustomerCartNetworkError(error) || isCustomerCartAuthError(error);
}

export const STALE_CART_AUTH_RECOVERY_MESSAGE =
  "Your session expired. The item was saved to your cart. Sign in to sync it.";

export function getCustomerCartErrorMessage(
  error: unknown,
  fallback = "Unable to sync your cart.",
): string {
  if (isCustomerCartAuthError(error)) {
    return STALE_CART_AUTH_RECOVERY_MESSAGE;
  }

  if (isCustomerCartApiError(error)) {
    return error.message.trim() || fallback;
  }

  if (error instanceof Error && error.message.trim()) {
    return error.message.trim();
  }

  return fallback;
}

export type CartSyncFailureResolution =
  | { kind: "fallback_local" }
  | { kind: "fallback_local_stale_auth"; message: string }
  | { kind: "keep_server"; message: string };

export function resolveCartSyncFailure(
  error: unknown,
  fallback = "Unable to sync your cart.",
): CartSyncFailureResolution {
  if (isCustomerCartAuthError(error)) {
    return {
      kind: "fallback_local_stale_auth",
      message: STALE_CART_AUTH_RECOVERY_MESSAGE,
    };
  }

  if (shouldFallbackToLocalCartOnError(error)) {
    return { kind: "fallback_local" };
  }

  return {
    kind: "keep_server",
    message: getCustomerCartErrorMessage(error, fallback),
  };
}

export function hasBlockingCartSyncError(syncError: string | null | undefined): syncError is string {
  const message = syncError?.trim();
  if (!message) {
    return false;
  }

  // Informational guest recovery — cart write succeeded locally; do not block checkout.
  if (message === STALE_CART_AUTH_RECOVERY_MESSAGE) {
    return false;
  }

  return true;
}
