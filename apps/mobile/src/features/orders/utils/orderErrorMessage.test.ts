import { ApiError } from '@/src/core/errors';
import {
  getOrderErrorMessage,
  isOrderCancellationRejected,
  isOrderUnauthenticatedError,
} from './orderErrorMessage';

describe('getOrderErrorMessage', () => {
  it('maps contract codes to customer messages without exposing codes', () => {
    expect(
      getOrderErrorMessage(
        new ApiError({
          message: 'Please sign in.',
          status: 401,
          code: 'unauthenticated',
        }),
      ),
    ).toBe('Please sign in.');

    expect(
      getOrderErrorMessage(
        new ApiError({
          message: 'This order can no longer be cancelled.',
          status: 422,
          code: 'business_rule_violated',
        }),
      ),
    ).toBe('This order can no longer be cancelled.');

    expect(
      getOrderErrorMessage(
        new ApiError({
          message: 'Missing.',
          status: 404,
          code: 'not_found',
        }),
      ),
    ).toBe('Missing.');

    const message = getOrderErrorMessage(
      new ApiError({
        message: 'Down',
        status: 503,
        code: 'maintenance_mode',
      }),
    );
    expect(message).not.toContain('maintenance_mode');
    expect(message.length).toBeGreaterThan(0);
  });
});

describe('cancellation rejection helpers', () => {
  it('detects business_rule_violated cancel rejection', () => {
    const rejected = new ApiError({
      message: 'This order can no longer be cancelled.',
      status: 422,
      code: 'business_rule_violated',
    });
    expect(isOrderCancellationRejected(rejected)).toBe(true);
    expect(isOrderUnauthenticatedError(rejected)).toBe(false);
  });

  it('detects unauthenticated for login redirect', () => {
    const error = new ApiError({
      message: 'Unauthenticated.',
      status: 401,
      code: 'unauthenticated',
    });
    expect(isOrderUnauthenticatedError(error)).toBe(true);
  });
});
