import { ApiError, getSharedTransportErrorMessage } from '@/src/core/errors';
import {
  isPaymentInProgressError,
  paymentInProgressCustomerMessage,
} from './payNowRecovery';

export function getPaymentErrorMessage(error: unknown): string {
  const transport = getSharedTransportErrorMessage(error);
  if (transport) return transport;

  if (isPaymentInProgressError(error)) {
    return paymentInProgressCustomerMessage();
  }

  if (error instanceof ApiError) {
    switch (error.code) {
      case 'unauthenticated':
        return error.message || 'Please sign in to continue payment.';
      case 'payment_failed':
        return error.message || 'Payment was not completed. Please try again.';
      case 'business_rule_violated': {
        const message = error.message?.trim() || '';
        if (/shipping/i.test(message)) {
          return message || 'Complete shipping selection before payment.';
        }
        return message || 'This payment cannot be started right now.';
      }
      case 'validation_failed': {
        const first = Object.values(error.errors).flat()[0];
        return first || error.message || 'Please check payment details and try again.';
      }
      case 'not_found':
        return "We couldn't find that payment. Please try again from your order.";
      case 'maintenance_mode':
        return error.message || 'The store is temporarily under maintenance.';
      case 'server_error':
        return error.message || 'Something went wrong while processing payment.';
      default:
        return error.message || 'Unable to process payment. Please try again.';
    }
  }

  if (error instanceof Error && error.message.trim()) {
    return error.message;
  }

  return 'Unable to process payment. Please try again.';
}

export function isPaymentUnauthenticatedError(error: unknown): boolean {
  return error instanceof ApiError && error.isUnauthenticated;
}
