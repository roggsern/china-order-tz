import { ApiError } from '@/src/core/errors';
import {
  getCartErrorMessage,
  isCartUnauthenticatedError,
} from './cartErrorMessage';

describe('getCartErrorMessage', () => {
  it('maps unauthenticated cart errors', () => {
    expect(
      getCartErrorMessage(
        new ApiError({
          message: '',
          status: 401,
          code: 'unauthenticated',
        }),
      ),
    ).toMatch(/sign in/i);
    expect(
      isCartUnauthenticatedError(
        new ApiError({
          message: 'Auth required',
          status: 401,
          code: 'unauthenticated',
        }),
      ),
    ).toBe(true);
  });

  it('maps business_rule_violated mixed-journey carts without exposing codes', () => {
    const message = getCartErrorMessage(
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

  it('maps generic business_rule_violated messages', () => {
    expect(
      getCartErrorMessage(
        new ApiError({
          message: 'Variant is out of stock.',
          status: 422,
          code: 'business_rule_violated',
        }),
      ),
    ).toBe('Variant is out of stock.');
  });
});
