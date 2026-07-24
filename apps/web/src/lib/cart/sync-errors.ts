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
 * Validation/business failures from the cart API (422 mixed channel, stock, etc.).
 */
export function isCustomerCartBusinessError(error: unknown): boolean {
  return isCustomerCartApiError(error) && !isCustomerCartNetworkError(error);
}

export function shouldFallbackToLocalCartOnError(error: unknown): boolean {
  return isCustomerCartNetworkError(error);
}

export function getCustomerCartErrorMessage(
  error: unknown,
  fallback = "Unable to sync your cart.",
): string {
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
  | { kind: "keep_server"; message: string };

export function resolveCartSyncFailure(
  error: unknown,
  fallback = "Unable to sync your cart.",
): CartSyncFailureResolution {
  if (shouldFallbackToLocalCartOnError(error)) {
    return { kind: "fallback_local" };
  }

  return {
    kind: "keep_server",
    message: getCustomerCartErrorMessage(error, fallback),
  };
}

export function hasBlockingCartSyncError(syncError: string | null | undefined): syncError is string {
  return Boolean(syncError?.trim());
}
