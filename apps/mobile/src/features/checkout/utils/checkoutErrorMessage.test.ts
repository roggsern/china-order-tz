import { ApiError } from '@/src/core/errors';
import {
  getCheckoutErrorMessage,
  isCheckoutUnauthenticatedError,
  isEmptyCartCheckoutError,
  isMissingDeliveryAddressError,
} from './checkoutErrorMessage';

describe('getCheckoutErrorMessage', () => {
  it('maps unauthenticated checkout', () => {
    const error = new ApiError({
      message: '',
      status: 401,
      code: 'unauthenticated',
    });
    expect(getCheckoutErrorMessage(error)).toMatch(/sign in/i);
    expect(isCheckoutUnauthenticatedError(error)).toBe(true);
  });

  it('maps validation_failed with field errors', () => {
    expect(
      getCheckoutErrorMessage(
        new ApiError({
          message: 'The given data was invalid.',
          status: 422,
          code: 'validation_failed',
          errors: { shipping_method: ['Company shipping requires air or sea.'] },
        }),
      ),
    ).toMatch(/air or sea/i);
  });

  it('maps business_rule_violated without exposing codes', () => {
    const message = getCheckoutErrorMessage(
      new ApiError({
        message: 'Cannot mix CHINA_IMPORT and TZ_LOCAL lines in one cart.',
        status: 422,
        code: 'business_rule_violated',
      }),
    );
    expect(message).toBe(
      'Your cart cannot contain products from different journeys.',
    );
    expect(message).not.toMatch(/business_rule_violated/i);
  });

  it('detects missing delivery address and empty cart', () => {
    expect(
      isMissingDeliveryAddressError(
        new ApiError({
          message: 'Delivery address is required before checkout.',
          status: 422,
          code: 'business_rule_violated',
          errors: {
            delivery_address: ['Delivery address is required before checkout.'],
          },
        }),
      ),
    ).toBe(true);

    expect(
      isEmptyCartCheckoutError(
        new ApiError({
          message: 'Cart is empty.',
          status: 422,
          code: 'business_rule_violated',
          errors: { cart: ['Cart is empty.'] },
        }),
      ),
    ).toBe(true);
  });

  it('maps expired/stale session messaging', () => {
    expect(
      getCheckoutErrorMessage(
        new ApiError({
          message: 'Checkout session has expired. Start checkout again.',
          status: 422,
          code: 'business_rule_violated',
          errors: { session: ['Checkout session has expired.'] },
        }),
      ),
    ).toMatch(/expired|refresh/i);
  });
});
