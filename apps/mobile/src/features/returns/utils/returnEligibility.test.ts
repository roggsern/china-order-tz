import {
  clampReturnQuantity,
  isReturnableOrderItemId,
  isSupportedReturnReason,
  shouldOfferReturnRequest,
} from './returnEligibility';
import { RETURN_REASON_OPTIONS } from '../models/types';

describe('shouldOfferReturnRequest', () => {
  it('shows Request return for delivered and completed orders', () => {
    expect(shouldOfferReturnRequest('delivered')).toBe(true);
    expect(shouldOfferReturnRequest('completed')).toBe(true);
  });

  it('hides the return action for ineligible and terminal statuses', () => {
    expect(shouldOfferReturnRequest('processing')).toBe(false);
    expect(shouldOfferReturnRequest('shipped')).toBe(false);
    expect(shouldOfferReturnRequest('pending_payment')).toBe(false);
    expect(shouldOfferReturnRequest('cancelled')).toBe(false);
    expect(shouldOfferReturnRequest('refunded')).toBe(false);
    expect(shouldOfferReturnRequest('refund_pending')).toBe(false);
  });

  it('applies the same web contract for CHINA_IMPORT and TZ_LOCAL', () => {
    expect(shouldOfferReturnRequest('delivered')).toBe(true);
    expect(shouldOfferReturnRequest('completed')).toBe(true);
  });
});

describe('return item and quantity mapping', () => {
  it('maps only backend UUID order item IDs', () => {
    expect(isReturnableOrderItemId('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee')).toBe(
      true,
    );
    expect(isReturnableOrderItemId('line-1')).toBe(false);
  });

  it('clamps quantity to backend line limits', () => {
    expect(clampReturnQuantity(0, 3)).toBe(1);
    expect(clampReturnQuantity(2, 3)).toBe(2);
    expect(clampReturnQuantity(9, 3)).toBe(3);
  });

  it('uses backend-supported free-text reasons from the web contract', () => {
    for (const reason of RETURN_REASON_OPTIONS) {
      expect(isSupportedReturnReason(reason)).toBe(true);
    }
    expect(isSupportedReturnReason('')).toBe(false);
  });
});
