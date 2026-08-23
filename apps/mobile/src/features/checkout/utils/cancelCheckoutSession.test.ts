import { QueryClient } from '@tanstack/react-query';
import { apiClient } from '@/src/core/api';
import { ApiError } from '@/src/core/errors';
import {
  cancelCheckoutSession,
  cancelCheckoutSessionSafely,
} from '../api/checkoutApi';
import {
  isCheckoutSessionAlreadyGone,
  isCompletedCheckoutSessionCancelError,
  shouldCancelCheckoutSession,
} from './cancelCheckoutSession';
import {
  checkoutPrepareQueryKey,
  checkoutSessionQueryKey,
  invalidateAfterCheckoutCancel,
} from './checkoutQueryKeys';

jest.mock('@/src/core/api', () => ({
  apiClient: {
    delete: jest.fn(),
  },
}));

const mockDelete = apiClient.delete as jest.MockedFunction<typeof apiClient.delete>;

describe('shouldCancelCheckoutSession', () => {
  it('cancels an active unfinished session', () => {
    expect(
      shouldCancelCheckoutSession({
        sessionId: 'sess-1',
        sessionStatus: 'validated',
      }),
    ).toBe(true);
  });

  it('does not cancel a completed session (order already created)', () => {
    expect(
      shouldCancelCheckoutSession({
        sessionId: 'sess-1',
        sessionStatus: 'completed',
      }),
    ).toBe(false);
  });

  it('does not cancel when an order id is already bound', () => {
    expect(
      shouldCancelCheckoutSession({
        sessionId: 'sess-1',
        orderId: 'ord-9',
      }),
    ).toBe(false);
  });
});

describe('cancelCheckoutSession', () => {
  beforeEach(() => {
    mockDelete.mockReset();
  });

  it('DELETEs an active checkout session', async () => {
    mockDelete.mockResolvedValue({ success: true, message: 'Checkout session cancelled.' } as never);

    await cancelCheckoutSession('sess-1');

    expect(mockDelete).toHaveBeenCalledWith('/checkout/sess-1');
  });

  it('treats already-cancelled or expired-missing sessions as already_gone', async () => {
    mockDelete.mockRejectedValue(
      new ApiError({
        message: 'Checkout session not found.',
        status: 404,
        code: 'not_found',
      }),
    );

    await expect(cancelCheckoutSessionSafely('sess-gone')).resolves.toBe('already_gone');
    expect(
      isCheckoutSessionAlreadyGone(
        new ApiError({
          message: 'Checkout session not found.',
          status: 404,
          code: 'not_found',
        }),
      ),
    ).toBe(true);
  });

  it('does not treat completed-session 422 as a successful cancel', async () => {
    const error = new ApiError({
      message: 'Completed checkout sessions cannot be cancelled.',
      status: 422,
      code: 'business_rule_violated',
    });
    mockDelete.mockRejectedValue(error);

    await expect(cancelCheckoutSessionSafely('sess-done')).rejects.toBe(error);
    expect(isCompletedCheckoutSessionCancelError(error)).toBe(true);
  });
});

describe('invalidateAfterCheckoutCancel', () => {
  it('removes the session query and invalidates prepare without emptying cart', async () => {
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    const cart = {
      id: 'cart-1',
      status: 'active',
      currency: 'TZS',
      items: [{ id: 'item-1' }],
      itemCount: 1,
      isEmpty: false,
      subtotal: '10',
      total: '10',
    };
    const cartKey = ['cart', 'current'] as const;
    queryClient.setQueryData(cartKey, cart);
    queryClient.setQueryData(checkoutSessionQueryKey('sess-1'), { id: 'sess-1' });
    queryClient.setQueryData(checkoutPrepareQueryKey(), { items: [] });

    await invalidateAfterCheckoutCancel(queryClient, 'sess-1');

    expect(queryClient.getQueryData(checkoutSessionQueryKey('sess-1'))).toBeUndefined();
    expect(queryClient.getQueryState(checkoutPrepareQueryKey())?.isInvalidated).toBe(true);
    expect(queryClient.getQueryData(cartKey)).toEqual(cart);
  });
});
