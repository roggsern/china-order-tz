import { createExclusiveLock, runExclusive } from '@/src/core/async/exclusiveLock';
import { shouldRetryMutation } from '@/src/core/api/queryRetryPolicy';
import { ApiError } from '@/src/core/errors';
import { ensurePaymentOrder } from './ensurePaymentOrder';
import { resolvePayNowView } from './payNowRecovery';

describe('payment reliability', () => {
  it('reopen of a processing transaction restores backend state instead of starting again', () => {
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
      paymentStatus: 'initiated',
      activeTransaction: {
        id: 'txn-backend-1',
        status: 'processing',
        provider: 'snippe',
      },
    });

    expect(view).toEqual({
      kind: 'recovery',
      transaction: {
        id: 'txn-backend-1',
        status: 'processing',
        provider: 'snippe',
      },
    });
  });

  it('does not create a second payment after a network failure', async () => {
    const lock = createExclusiveLock();
    let starts = 0;

    await expect(
      runExclusive(lock, async () => {
        starts += 1;
        throw new ApiError({
          message: 'Network request failed',
          status: 0,
          code: 'network_error',
        });
      }),
    ).rejects.toBeInstanceOf(ApiError);

    expect(starts).toBe(1);
    expect(shouldRetryMutation()).toBe(false);
    expect(lock.isHeld()).toBe(false);
  });

  it('places an order from checkout only once while a start is in flight', async () => {
    const lock = createExclusiveLock();
    let placements = 0;
    const place = async () => {
      placements += 1;
      return ensurePaymentOrder({
        orderId: 'ord-1',
        checkoutSessionId: 'chk-1',
      });
    };

    const [first, second] = await Promise.all([
      runExclusive(lock, place),
      runExclusive(lock, place),
    ]);

    expect(placements).toBe(1);
    expect(first).toMatchObject({ id: 'ord-1' });
    expect(second).toBe('busy');
  });
});
