/* eslint-disable import/first -- jest.mock must hoist before imports under test */
jest.mock('../api/paymentsApi', () => ({
  createOrderFromCheckoutSession: jest.fn().mockResolvedValue({
    id: 'ord-from-checkout',
    orderNumber: 'COTZ-1',
    status: 'pending_payment',
    currency: 'TZS',
    grandTotal: '1000',
    checkoutSessionId: 'sess-1',
  }),
}));

import { createOrderFromCheckoutSession } from '../api/paymentsApi';
import { ensurePaymentOrder } from './ensurePaymentOrder';

describe('ensurePaymentOrder', () => {
  it('uses an existing order id and does not create another order', async () => {
    const order = await ensurePaymentOrder({ orderId: 'ord-1' });
    expect(order.id).toBe('ord-1');
    expect(createOrderFromCheckoutSession).not.toHaveBeenCalled();
  });

  it('creates from checkout only when no order exists', async () => {
    const order = await ensurePaymentOrder({ checkoutSessionId: 'sess-1' });
    expect(order.id).toBe('ord-from-checkout');
    expect(createOrderFromCheckoutSession).toHaveBeenCalledWith('sess-1');
  });
});
