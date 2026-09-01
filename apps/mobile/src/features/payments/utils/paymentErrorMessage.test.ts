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
    expect(message).toMatch(/couldn't connect/i);
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
    expect(message).toMatch(/already in progress/i);
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

  it('maps place-order purchase_quantity_unsatisfied from structured data', () => {
    expect(
      getPaymentErrorMessage(
        new ApiError({
          message: 'This product does not meet the purchase quantity rule.',
          status: 422,
          code: 'purchase_quantity_unsatisfied',
          raw: {
            code: 'purchase_quantity_unsatisfied',
            message: 'This product does not meet the purchase quantity rule.',
            data: {
              purchase_quantity: {
                product_id: 'p1',
                minimum_quantity: 6,
                increment: 3,
                eligible_quantity: 7,
                minimum_satisfied: true,
                increment_satisfied: false,
                quantity_to_minimum: 0,
                next_legal_quantity: 9,
                blocks_checkout: true,
              },
            },
          },
        }),
      ),
    ).toBe('Next allowed quantity is 9.');
    expect(
      getPaymentErrorMessage(
        new ApiError({
          message: 'This product does not meet the purchase quantity rule.',
          status: 422,
          code: 'purchase_quantity_unsatisfied',
          raw: {
            code: 'purchase_quantity_unsatisfied',
            message: 'This product does not meet the purchase quantity rule.',
            data: {
              purchase_quantity: {
                product_id: 'p1',
                minimum_quantity: 6,
                increment: 3,
                eligible_quantity: 7,
                minimum_satisfied: true,
                increment_satisfied: false,
                quantity_to_minimum: 0,
                next_legal_quantity: 9,
                blocks_checkout: true,
              },
            },
          },
        }),
      ),
    ).not.toMatch(/NMB|Snippe|Pay at Office|Payment failed|charged/i);
  });
});
