import {
  resolveRefundDisplayStatus,
  resolveReturnDisplayStatus,
} from './returnStatusDisplay';
import { resolveOrderDisplayStatus } from '@/src/features/orders/utils/orderLifecycleDisplay';

describe('return and refund status separation', () => {
  it('keeps return requested distinct from order refunded', () => {
    expect(resolveReturnDisplayStatus('requested')).toEqual({
      key: 'requested',
      label: 'Return requested',
    });
    expect(resolveOrderDisplayStatus({ status: 'delivered' }).key).not.toBe(
      'refunded',
    );
    expect(resolveReturnDisplayStatus('requested').key).not.toBe('refunded');
  });

  it('does not invent a local refund when none was returned', () => {
    expect(resolveRefundDisplayStatus(null)).toEqual({
      key: 'none',
      label: 'No refund yet',
    });
  });

  it('keeps refund_pending / refund pending display authoritative', () => {
    expect(resolveRefundDisplayStatus('pending').label).toBe('Refund pending');
    expect(resolveRefundDisplayStatus('pending').key).not.toBe('failed');
    expect(resolveOrderDisplayStatus({ status: 'refund_pending' }).label).toBe(
      'Refund in progress',
    );
  });
});
