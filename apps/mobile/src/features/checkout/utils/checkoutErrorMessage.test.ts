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

  it('shows a shipping-choice validation error without inventing a fee', () => {
    expect(
      getCheckoutErrorMessage(
        new ApiError({
          message: 'The given data was invalid.',
          status: 422,
          code: 'validation_failed',
          errors: {
            shipping_choice: ['The selected shipping choice is invalid for this cart.'],
          },
        }),
      ),
    ).toBe('The selected shipping choice is invalid for this cart.');
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
    ).toMatch(/timed out|totals changed/i);
  });

  it('maps purchase_quantity_unsatisfied from structured data, not English API copy', () => {
    expect(
      getCheckoutErrorMessage(
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
                increment: null,
                eligible_quantity: 4,
                minimum_satisfied: false,
                increment_satisfied: true,
                quantity_to_minimum: 2,
                next_legal_quantity: 6,
                blocks_checkout: true,
              },
            },
          },
        }),
      ),
    ).toBe('Add 2 more before checkout.');
  });

  it('falls back without crashing when purchase_quantity payload is missing', () => {
    expect(
      getCheckoutErrorMessage(
        new ApiError({
          message: 'This product does not meet the purchase quantity rule.',
          status: 422,
          code: 'purchase_quantity_unsatisfied',
          raw: {
            code: 'purchase_quantity_unsatisfied',
            message: 'This product does not meet the purchase quantity rule.',
          },
        }),
      ),
    ).toBe('This product does not meet the purchase quantity rule.');
  });
});
