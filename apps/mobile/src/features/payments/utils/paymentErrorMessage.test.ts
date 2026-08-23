import { ApiError } from '@/src/core/errors';
import {
  getPaymentErrorMessage,
  isPaymentUnauthenticatedError,
} from './paymentErrorMessage';

describe('getPaymentErrorMessage', () => {
  it('maps unauthenticated payment', () => {
    const error = new ApiError({
      message: '',
      status: 401,
      code: 'unauthenticated',
    });
    expect(getPaymentErrorMessage(error)).toMatch(/sign in/i);
    expect(isPaymentUnauthenticatedError(error)).toBe(true);
  });

  it('maps payment_failed without exposing codes', () => {
    const message = getPaymentErrorMessage(
      new ApiError({
        message: '',
        status: 422,
        code: 'payment_failed',
      }),
    );
    expect(message).toMatch(/not completed/i);
    expect(message).not.toMatch(/payment_failed/i);
  });

  it('does not label timeout as offline', () => {
    const message = getPaymentErrorMessage(
      new ApiError({
        message: 'Request timed out. Please try again.',
        status: 0,
        code: 'timeout',
      }),
    );
    expect(message).toMatch(/timed out/i);
    expect(message).not.toMatch(/No internet/i);
  });

  it('maps payment_in_progress to recovery copy without exposing the code', () => {
    const message = getPaymentErrorMessage(
      new ApiError({
        message: 'An active payment is already in progress for this order.',
        status: 422,
        code: 'payment_in_progress',
      }),
    );
    expect(message).toMatch(/payment request pending/i);
    expect(message).not.toMatch(/payment_in_progress/i);
  });

  it('maps business_rule_violated', () => {
    expect(
      getPaymentErrorMessage(
        new ApiError({
          message: 'Shipping choice is required before payment.',
          status: 422,
          code: 'business_rule_violated',
        }),
      ),
    ).toMatch(/shipping/i);
  });
});
