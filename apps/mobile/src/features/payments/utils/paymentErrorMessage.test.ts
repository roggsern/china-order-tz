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
