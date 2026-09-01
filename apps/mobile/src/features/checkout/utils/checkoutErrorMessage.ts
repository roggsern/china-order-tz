import { ApiError, getSharedTransportErrorMessage } from '@/src/core/errors';
import { purchaseQuantityMessageFromError } from '@/src/features/purchasing/purchaseQuantity';
import { isStaleOrExpiredCheckoutError } from './mapCheckout';

export function getCheckoutErrorMessage(error: unknown): string {
  const transport = getSharedTransportErrorMessage(error);
  if (transport) return transport;

  const purchaseQuantityMessage = purchaseQuantityMessageFromError(error);
  if (purchaseQuantityMessage) {
    return purchaseQuantityMessage;
  }

  if (!(error instanceof ApiError)) {
    return 'Unable to continue checkout. Please try again.';
  }

  if (isStaleOrExpiredCheckoutError(error)) {
    return 'Your checkout timed out or totals changed. Please review and continue.';
  }

  switch (error.code) {
    case 'unauthenticated':
      return error.message || 'Please sign in to continue checkout.';
    case 'business_rule_violated': {
      const message = error.message?.trim() || '';
      if (/mix|different|channel|journey|CHINA|TZ_LOCAL|commerce/i.test(message)) {
        return 'Your cart cannot contain products from different journeys.';
      }
      if (/empty|cart/i.test(message)) {
        return message || 'Your cart is empty.';
      }
      if (/delivery_address|address/i.test(message)) {
        return message || 'Add a delivery address to continue checkout.';
      }
      if (/shipping/i.test(message)) {
        return message || 'Please choose a valid shipping option.';
      }
      return message || 'This checkout step is not allowed right now.';
    }
    case 'validation_failed': {
      const firstFieldError = Object.values(error.errors).flat()[0];
      return (
        firstFieldError ||
        error.message ||
        'Please check your checkout details and try again.'
      );
    }
    case 'not_found':
      return "We couldn't find your checkout. Please start again.";
    case 'maintenance_mode':
      return error.message || 'The store is temporarily under maintenance.';
    case 'server_error':
      return error.message || 'Something went wrong during checkout.';
    default:
      return error.message || 'Unable to continue checkout. Please try again.';
  }
}

export function isCheckoutUnauthenticatedError(error: unknown): boolean {
  return error instanceof ApiError && error.isUnauthenticated;
}

export function isMissingDeliveryAddressError(error: unknown): boolean {
  if (!(error instanceof ApiError)) return false;
  if (error.errors.delivery_address?.length) return true;
  return /delivery address/i.test(error.message);
}

export function isEmptyCartCheckoutError(error: unknown): boolean {
  if (!(error instanceof ApiError)) return false;
  if (error.errors.cart?.some((msg) => /empty/i.test(msg))) return true;
  return /cart is empty/i.test(error.message);
}
