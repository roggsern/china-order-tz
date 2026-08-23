import { isOrderPayableFromServer } from './isOrderPayable';
import {
  buildOrderLifecyclePresentation,
  resolveFulfillmentDisplayStatus,
  resolveOrderDisplayStatus,
  resolvePaymentDisplayStatus,
  resolveProgressForDisplay,
  resolveReceivingDisplayStatus,
  resolveTrackingHeroLabel,
} from './orderLifecycleDisplay';
import { buildOrderListCardPresentation } from './orderCardPresentation';
import type { OrderDetail, OrderListItem, OrderProgress } from '../models/types';

function progress(
  currentKey: string,
  currentLabel: string,
  extra?: Partial<OrderProgress>,
): OrderProgress {
  return {
    currentKey,
    currentLabel,
    steps: extra?.steps ?? [{ key: currentKey, label: currentLabel, completed: false }],
  };
}

function listOrder(
  overrides: Partial<OrderListItem> & Pick<OrderListItem, 'id' | 'status'>,
): OrderListItem {
  return {
    orderNumber: overrides.orderNumber ?? 'COTZ-1',
    source: overrides.source ?? 'China',
    journeyLabel: overrides.journeyLabel ?? 'Order from China',
    statusLabel: overrides.statusLabel ?? null,
    paymentStatus: overrides.paymentStatus ?? null,
    currency: overrides.currency ?? 'TZS',
    subtotal: overrides.subtotal ?? '10000',
    grandTotal: overrides.grandTotal ?? '10000',
    createdAt: overrides.createdAt ?? null,
    preview: overrides.preview ?? null,
    progress: overrides.progress ?? null,
    canCancel: overrides.canCancel ?? null,
    canPay: overrides.canPay ?? null,
    activePaymentTransaction: overrides.activePaymentTransaction ?? null,
    receivingChoice: overrides.receivingChoice ?? null,
    ...overrides,
  };
}

function sameLifecycleFromListAndDetail(list: OrderListItem, detail: OrderDetail) {
  const fromList = buildOrderLifecyclePresentation({
    status: list.status,
    statusLabel: list.statusLabel,
    paymentStatus: list.paymentStatus,
    progress: list.progress,
    receivingChoice: list.receivingChoice,
  });
  const fromDetail = buildOrderLifecyclePresentation({
    status: detail.status,
    statusLabel: detail.statusLabel,
    paymentStatus: detail.payment?.paymentStatus,
    paymentMethod: detail.payment?.paymentMethod,
    paymentProvider: detail.payment?.provider,
    progress: detail.progress,
    shipment: detail.shipment,
    receivingChoice: detail.receivingChoice,
  });
  return { fromList, fromDetail };
}

describe('order display status', () => {
  it('maps pending_payment to Awaiting payment', () => {
    expect(resolveOrderDisplayStatus({ status: 'pending_payment' }).label).toBe(
      'Awaiting payment',
    );
    expect(resolveOrderDisplayStatus({ status: 'pending' }).label).toBe(
      'Awaiting payment',
    );
  });

  it('maps paid to Paid', () => {
    expect(resolveOrderDisplayStatus({ status: 'paid' }).label).toBe('Paid');
    expect(resolveOrderDisplayStatus({ status: 'confirmed' }).label).toBe('Paid');
  });

  it('maps cancelled to Cancelled even when status_label is stale', () => {
    expect(
      resolveOrderDisplayStatus({
        status: 'cancelled',
        statusLabel: 'Awaiting payment',
      }).label,
    ).toBe('Cancelled');
  });

  it('maps refunded to Refunded', () => {
    expect(resolveOrderDisplayStatus({ status: 'refunded' }).label).toBe('Refunded');
  });

  it('maps refund_pending to Refund in progress', () => {
    expect(resolveOrderDisplayStatus({ status: 'refund_pending' }).label).toBe(
      'Refund in progress',
    );
  });

  it('keeps cancelled when a stale processing transaction exists', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'cancelled',
      statusLabel: 'Awaiting payment',
      paymentStatus: 'initiated',
      transactionStatus: 'processing',
    });
    expect(display.order.label).toBe('Cancelled');
    expect(display.order.key).toBe('cancelled');
  });

  it('keeps refunded when a stale processing transaction exists', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'refunded',
      paymentStatus: 'refunded',
      transactionStatus: 'processing',
    });
    expect(display.order.label).toBe('Refunded');
  });

  it('keeps cancelled when a late successful transaction exists', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'cancelled',
      paymentStatus: 'paid',
      transactionStatus: 'successful',
    });
    expect(display.order.label).toBe('Cancelled');
  });
});

describe('payment display status', () => {
  it('shows awaiting payment for initiated payable orders', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'pending_payment',
        paymentStatus: 'initiated',
      }).label,
    ).toBe('Awaiting payment');
  });

  it('shows awaiting payment for an active processing transaction', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'pending_payment',
        paymentStatus: 'initiated',
        transactionStatus: 'processing',
      }).label,
    ).toBe('Awaiting payment');
  });

  it('shows Paid for successful payment', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'paid',
        paymentStatus: 'paid',
      }).label,
    ).toBe('Paid');
  });

  it('shows payment not completed for failed payment', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'pending_payment',
        paymentStatus: 'failed',
      }).label,
    ).toBe('Payment not completed');
  });

  it('shows payment not completed for cancelled unpaid + stale processing txn', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'cancelled',
        paymentStatus: 'initiated',
        transactionStatus: 'processing',
        paymentMethod: 'nmb',
      }),
    ).toEqual({
      key: 'not_completed',
      label: 'Payment not completed',
      methodLabel: 'NMB Bank',
    });
  });

  it('shows Paid for cancelled order with late successful txn', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'cancelled',
        paymentStatus: 'paid',
        transactionStatus: 'successful',
        paymentMethod: 'nmb',
      }),
    ).toMatchObject({
      key: 'paid',
      label: 'Paid',
      methodLabel: 'NMB Bank',
    });
  });

  it('shows awaiting payment for Pay at Office before confirmation', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'pending_payment',
        paymentStatus: 'initiated',
        paymentMethod: 'cash',
      }),
    ).toMatchObject({
      key: 'pending',
      label: 'Awaiting payment',
      methodLabel: 'Pay at Office',
    });
  });

  it('shows Paid for Pay at Office after backend confirmation', () => {
    expect(
      resolvePaymentDisplayStatus({
        orderStatus: 'paid',
        paymentStatus: 'paid',
        paymentMethod: 'cash',
      }),
    ).toMatchObject({
      key: 'paid',
      label: 'Paid',
      methodLabel: 'Pay at Office',
    });
  });
});

describe('fulfillment display status', () => {
  it('shows not started while payment is pending', () => {
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'pending_payment',
        progress: progress('AWAITING_PAYMENT', 'Awaiting payment'),
      }),
    ).toMatchObject({
      label: 'Not started',
      isActive: false,
      showProgression: false,
    });
  });

  it('shows backend preparing label when paid and fulfillment is processing', () => {
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'processing',
        progress: progress('PREPARING', 'Preparing your order'),
      }),
    ).toMatchObject({
      key: 'PREPARING',
      label: 'Preparing your order',
      showProgression: true,
    });
  });

  it('shows Shipped from backend progress', () => {
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'shipped',
        progress: progress('SHIPPED', 'Shipped'),
      }).label,
    ).toBe('Shipped');
  });

  it('shows Delivered from backend progress', () => {
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'delivered',
        progress: progress('DELIVERED', 'Delivered'),
      }),
    ).toMatchObject({
      label: 'Delivered',
      isActive: false,
      showProgression: true,
    });
  });

  it('does not show active fulfillment progression for cancelled orders', () => {
    const display = resolveFulfillmentDisplayStatus({
      orderStatus: 'cancelled',
      progress: progress('AWAITING_PAYMENT', 'Awaiting payment'),
    });
    expect(display.isActive).toBe(false);
    expect(display.showProgression).toBe(false);
    expect(display.label).toBe('Not started');
    expect(resolveProgressForDisplay('cancelled', progress('AWAITING_PAYMENT', 'Awaiting payment'))).toBeNull();
  });

  it('uses TZ_LOCAL progress labels from the backend', () => {
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'processing',
        progress: progress('READY_TO_SHIP', 'Order ready'),
      }).label,
    ).toBe('Order ready');
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'delivered',
        progress: progress('DELIVERED', 'Completed'),
      }).label,
    ).toBe('Completed');
  });

  it('uses CHINA_IMPORT progress labels from the backend', () => {
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'shipped',
        progress: progress('SHIPPED', 'Shipped'),
      }).label,
    ).toBe('Shipped');
    expect(
      resolveFulfillmentDisplayStatus({
        orderStatus: 'processing',
        progress: progress('ARRIVED_TANZANIA', 'Arrived in Tanzania'),
      }).label,
    ).toBe('Arrived in Tanzania');
  });
});

describe('list and detail consistency', () => {
  it('uses the same order display status on list and detail', () => {
    const list = listOrder({
      id: 'ord-1',
      status: 'cancelled',
      statusLabel: 'Awaiting payment',
      paymentStatus: 'initiated',
    });
    const detail: OrderDetail = {
      id: 'ord-1',
      orderNumber: 'COTZ-1',
      source: 'China',
      journeyLabel: 'Order from China',
      status: 'cancelled',
      statusLabel: 'Awaiting payment',
      createdAt: null,
      items: [],
      summary: {
        subtotal: null,
        shipping: null,
        tax: null,
        discount: null,
        grandTotal: '10000',
      },
      payment: {
        paymentStatus: 'initiated',
        paymentMethod: 'nmb',
        reference: null,
        provider: 'nmb',
        amount: '10000',
        currency: 'TZS',
        paidAt: null,
        initiatedAt: null,
      },
      progress: progress('CANCELLED', 'Order cancelled'),
      shipment: null,
      currency: 'TZS',
      canCancel: false,
      canPay: false,
      activePaymentTransaction: {
        id: 'txn-stale',
        status: 'processing',
        provider: 'nmb',
      },
      receivingChoice: null,
    };

    const { fromList, fromDetail } = sameLifecycleFromListAndDetail(list, detail);
    expect(fromList.order.label).toBe(fromDetail.order.label);
    expect(fromList.order.label).toBe('Cancelled');
    expect(buildOrderListCardPresentation(list).statusLabel).toBe('Cancelled');
  });

  it('hides Pay Now on cancelled and refunded orders', () => {
    expect(
      isOrderPayableFromServer({
        status: 'cancelled',
        canPay: false,
        paymentStatus: 'initiated',
      }),
    ).toBe(false);
    expect(
      isOrderPayableFromServer({
        status: 'refunded',
        canPay: false,
        paymentStatus: 'refunded',
      }),
    ).toBe(false);
  });

  it('shows Pay Now only when can_pay is true', () => {
    expect(
      isOrderPayableFromServer({
        status: 'pending_payment',
        canPay: true,
        paymentStatus: 'initiated',
      }),
    ).toBe(true);
    expect(
      isOrderPayableFromServer({
        status: 'pending_payment',
        canPay: false,
        paymentStatus: 'initiated',
      }),
    ).toBe(false);
  });

  it('does not let a stale active transaction override a terminal order', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'cancelled',
      paymentStatus: 'initiated',
      transactionStatus: 'processing',
    });
    expect(display.order.label).toBe('Cancelled');
    expect(display.payment.label).toBe('Payment not completed');
    expect(
      isOrderPayableFromServer({
        status: 'cancelled',
        canPay: true,
        paymentStatus: 'initiated',
      }),
    ).toBe(false);
  });
});

describe('tracking hero', () => {
  it('does not show active shipment copy for cancelled orders', () => {
    expect(
      resolveTrackingHeroLabel({
        orderStatus: 'cancelled',
        trackingCurrentLabel: 'On the way',
        trackingCurrentStatus: 'in_transit',
        progress: progress('AWAITING_PAYMENT', 'Awaiting payment'),
      }),
    ).toBe('Cancelled');
  });

  it('does not imply shipping has started for pending payment', () => {
    expect(
      resolveTrackingHeroLabel({
        orderStatus: 'pending_payment',
        trackingCurrentLabel: 'On the way',
        progress: progress('AWAITING_PAYMENT', 'Awaiting payment'),
      }),
    ).toBe('Awaiting payment');
  });

  it('uses tracking label once fulfillment has started', () => {
    expect(
      resolveTrackingHeroLabel({
        orderStatus: 'shipped',
        trackingCurrentLabel: 'On the way',
        progress: progress('SHIPPED', 'Shipped'),
      }),
    ).toBe('On the way');
  });
});

describe('receiving presentation', () => {
  it('shows action required when receiving choice is needed', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'shipped',
      progress: progress('CHOOSE_RECEIVING_METHOD', 'Choose receiving method'),
      receivingChoice: {
        eligible: true,
        canSelect: true,
        selectedMethod: null,
        selectedMethodLabel: null,
        selectedAt: null,
      },
    });
    expect(display.receiving.actionRequired).toBe(true);
    expect(display.headline.label).toBe('Action required');
    expect(display.order.key).toBe('shipped');
    expect(display.fulfillment.key).toBe('CHOOSE_RECEIVING_METHOD');
  });

  it('shows pickup state after self_pickup is selected', () => {
    expect(
      resolveReceivingDisplayStatus({
        orderStatus: 'shipped',
        receivingChoice: {
          eligible: true,
          canSelect: false,
          selectedMethod: 'self_pickup',
          selectedMethodLabel: 'Self Pickup',
          selectedAt: '2026-08-24T00:00:00Z',
        },
      }).label,
    ).toBe('Waiting for pickup');
  });

  it('shows backend delivery state after negotiated_delivery is selected', () => {
    expect(
      resolveReceivingDisplayStatus({
        orderStatus: 'shipped',
        receivingChoice: {
          eligible: true,
          canSelect: false,
          selectedMethod: 'negotiated_delivery',
          selectedMethodLabel: 'Negotiated Delivery',
          selectedAt: '2026-08-24T00:00:00Z',
        },
      }).label,
    ).toBe('Delivery arrangement pending');
  });

  it('does not invent receiving state for TZ_LOCAL without a snapshot', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'processing',
      progress: progress('PREPARING', 'Preparing your order'),
      receivingChoice: {
        eligible: false,
        canSelect: false,
        selectedMethod: null,
        selectedMethodLabel: null,
        selectedAt: null,
      },
    });
    expect(display.receiving.actionRequired).toBe(false);
    expect(display.receiving.label).toBeNull();
    expect(display.headline.label).toBe('Processing');
  });

  it('does not expose receiving action on cancelled or refunded orders', () => {
    expect(
      resolveReceivingDisplayStatus({
        orderStatus: 'cancelled',
        receivingChoice: {
          eligible: true,
          canSelect: true,
          selectedMethod: null,
          selectedMethodLabel: null,
          selectedAt: null,
        },
      }).actionRequired,
    ).toBe(false);
    expect(
      resolveReceivingDisplayStatus({
        orderStatus: 'refunded',
        receivingChoice: {
          eligible: true,
          canSelect: true,
          selectedMethod: null,
          selectedMethodLabel: null,
          selectedAt: null,
        },
      }).label,
    ).toBeNull();
  });
});

describe('status separation', () => {
  it('keeps order, payment, fulfillment, return, and refund layers distinct', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'delivered',
      paymentStatus: 'paid',
      progress: progress('DELIVERED', 'Delivered'),
    });
    expect(display.order.key).toBe('delivered');
    expect(display.payment.key).toBe('paid');
    expect(display.fulfillment.key).toBe('DELIVERED');
    expect(display.order.key).not.toBe(display.payment.key);
  });

  it('does not mark the order refunded when only a return is pending', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'delivered',
      paymentStatus: 'paid',
      progress: progress('DELIVERED', 'Delivered'),
    });
    expect(display.order.key).not.toBe('refunded');
    expect(display.payment.key).not.toBe('refunded');
    expect(display.order.label).toBe('Delivered');
  });

  it('keeps refund_pending display backend-authoritative', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'refund_pending',
      paymentStatus: 'paid',
    });
    expect(display.order.key).toBe('refund_pending');
    expect(display.order.label).toBe('Refund in progress');
    expect(display.payment.key).not.toBe('failed');
  });
});
