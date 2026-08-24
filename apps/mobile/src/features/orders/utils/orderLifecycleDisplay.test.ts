import { isOrderPayableFromServer } from './isOrderPayable';
import { shouldOfferCancel } from './mapOrders';
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
import { hasOrderTrackingEntry } from './hasOrderTrackingEntry';
import { shouldOfferReturnRequest } from '@/src/features/returns/utils/returnEligibility';
import type {
  OrderDetail,
  OrderListItem,
  OrderProgress,
  ReceivingChoiceSnapshot,
} from '../models/types';

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

  it('uses waiting-for-pickup headline when self pickup is still pending', () => {
    expect(
      resolveTrackingHeroLabel({
        orderStatus: 'shipped',
        trackingCurrentLabel: 'On the way',
        progress: progress('CHOOSE_RECEIVING_METHOD', 'Choose receiving method'),
        receivingChoice: {
          eligible: true,
          canSelect: false,
          selectedMethod: 'self_pickup',
          selectedMethodLabel: 'Self Pickup',
          selectedAt: '2026-08-24T00:00:00Z',
        },
      }),
    ).toBe('Waiting for pickup');
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

const SELF_PICKUP: ReceivingChoiceSnapshot = {
  eligible: false,
  canSelect: false,
  selectedMethod: 'self_pickup',
  selectedMethodLabel: 'Self Pickup',
  selectedAt: '2026-08-24T00:00:00Z',
};

function chinaCompletedProgress(): OrderProgress {
  return {
    currentKey: 'DELIVERED',
    currentLabel: 'Completed',
    steps: [
      { key: 'ORDER_CONFIRMED', label: 'Order confirmed', completed: true },
      { key: 'PREPARING', label: 'Preparing your order', completed: true },
      { key: 'SHIPPED', label: 'Shipped', completed: true },
      { key: 'ARRIVED_TANZANIA', label: 'Arrived in Tanzania', completed: true },
      {
        key: 'CHOOSE_RECEIVING_METHOD',
        label: 'Choose receiving method',
        completed: true,
      },
      { key: 'DELIVERED', label: 'Completed', completed: true },
    ],
  };
}

describe('Wave 8A terminal receiving and cancel precedence', () => {
  it('shows Waiting for pickup while self pickup is selected and handover is not terminal', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'shipped',
      paymentStatus: 'paid',
      progress: progress('CHOOSE_RECEIVING_METHOD', 'Choose receiving method'),
      receivingChoice: {
        ...SELF_PICKUP,
        eligible: true,
      },
    });
    expect(display.receiving.label).toBe('Waiting for pickup');
    expect(display.headline.label).toBe('Waiting for pickup');
    expect(display.receiving.selectedMethod).toBe('self_pickup');
    expect(display.order.key).toBe('shipped');
  });

  it('shows terminal receiving after self pickup + completed progress', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'completed',
      statusLabel: 'Completed',
      paymentStatus: 'paid',
      progress: chinaCompletedProgress(),
      shipment: { status: 'Completed', statusLabel: 'Completed' },
      receivingChoice: SELF_PICKUP,
    });
    expect(display.headline.label).toBe('Completed');
    expect(display.headline.label).not.toBe('Waiting for pickup');
    expect(display.order.label).toBe('Completed');
    expect(display.payment.label).toBe('Paid');
    expect(display.fulfillment.label).toBe('Completed');
    expect(display.receiving.label).toBe('Completed');
    expect(display.receiving.label).not.toBe('Waiting for pickup');
    expect(display.receiving.actionRequired).toBe(false);
    expect(display.receiving.selectedMethod).toBe('self_pickup');
  });

  it('hides Cancel Order for completed, delivered, cancelled, refunded, and refund_pending', () => {
    for (const status of [
      'completed',
      'delivered',
      'cancelled',
      'refunded',
      'refund_pending',
    ]) {
      expect(shouldOfferCancel({ status, canCancel: null })).toBe(false);
    }
  });

  it('keeps cancel available for pending unpaid orders', () => {
    expect(
      shouldOfferCancel({ status: 'pending_payment', canCancel: null }),
    ).toBe(true);
    expect(shouldOfferCancel({ status: 'pending', canCancel: null })).toBe(true);
  });

  it('keeps return eligibility backend-authoritative after delivered and completed', () => {
    expect(shouldOfferReturnRequest('delivered')).toBe(true);
    expect(shouldOfferReturnRequest('completed')).toBe(true);
    expect(shouldOfferReturnRequest('shipped')).toBe(false);
  });

  it('preserves historical Self Pickup after completion', () => {
    const receiving = resolveReceivingDisplayStatus({
      orderStatus: 'completed',
      progress: chinaCompletedProgress(),
      receivingChoice: SELF_PICKUP,
    });
    expect(receiving.selectedMethod).toBe('self_pickup');
    expect(receiving.key).toBe('completed');
  });

  it('does not let a stale receiving snapshot override terminal fulfillment', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'shipped',
      paymentStatus: 'paid',
      progress: chinaCompletedProgress(),
      receivingChoice: {
        eligible: true,
        canSelect: true,
        selectedMethod: 'self_pickup',
        selectedMethodLabel: 'Self Pickup',
        selectedAt: '2026-08-24T00:00:00Z',
      },
    });
    expect(display.fulfillment.label).toBe('Completed');
    expect(display.receiving.label).toBe('Completed');
    expect(display.receiving.actionRequired).toBe(false);
    expect(display.headline.label).not.toBe('Waiting for pickup');
    expect(display.headline.label).not.toBe('Action required');
    expect(
      shouldOfferCancel({
        status: 'shipped',
        canCancel: null,
        progress: chinaCompletedProgress(),
      }),
    ).toBe(false);
  });

  it('keeps list and detail lifecycle presentation in agreement for completed self pickup', () => {
    const list = listOrder({
      id: 'ord-8a',
      status: 'completed',
      statusLabel: 'Completed',
      paymentStatus: 'paid',
      source: 'China',
      journeyLabel: 'Order from China',
      progress: chinaCompletedProgress(),
      receivingChoice: SELF_PICKUP,
      canCancel: null,
    });
    const detail: OrderDetail = {
      id: 'ord-8a',
      orderNumber: 'COTZ-8A',
      source: 'China',
      journeyLabel: 'Order from China',
      status: 'completed',
      statusLabel: 'Completed',
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
        paymentStatus: 'paid',
        paymentMethod: 'nmb',
        reference: null,
        provider: 'nmb',
        amount: '10000',
        currency: 'TZS',
        paidAt: null,
        initiatedAt: null,
      },
      progress: chinaCompletedProgress(),
      shipment: {
        status: 'Completed',
        statusLabel: 'Completed',
        trackingReference: null,
        carrierName: null,
      },
      currency: 'TZS',
      canCancel: null,
      canPay: false,
      activePaymentTransaction: null,
      receivingChoice: SELF_PICKUP,
    };

    const { fromList, fromDetail } = sameLifecycleFromListAndDetail(list, detail);
    expect(fromList.headline.label).toBe(fromDetail.headline.label);
    expect(fromList.receiving.label).toBe(fromDetail.receiving.label);
    expect(fromList.fulfillment.label).toBe(fromDetail.fulfillment.label);
    expect(fromList.headline.label).toBe('Completed');
    expect(buildOrderListCardPresentation(list).statusLabel).toBe('Completed');
    expect(
      resolveTrackingHeroLabel({
        orderStatus: detail.status,
        trackingCurrentLabel: 'Completed',
        progress: detail.progress,
        receivingChoice: detail.receivingChoice,
      }),
    ).toBe('Completed');
    expect(hasOrderTrackingEntry(detail)).toBe(true);
  });

  it('applies CHINA_IMPORT company-shipping completed labels from backend progress', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'delivered',
      paymentStatus: 'paid',
      progress: chinaCompletedProgress(),
      receivingChoice: SELF_PICKUP,
    });
    expect(display.order.label).toBe('Delivered');
    expect(display.fulfillment.label).toBe('Completed');
    expect(display.receiving.label).toBe('Completed');
    expect(display.headline.label).toBe('Delivered');
    expect(shouldOfferCancel({ status: 'delivered', canCancel: null })).toBe(false);
    expect(shouldOfferReturnRequest('delivered')).toBe(true);
  });

  it('applies TZ_LOCAL completed presentation without inventing a receiving layer', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'completed',
      paymentStatus: 'paid',
      progress: progress('DELIVERED', 'Completed', {
        steps: [
          { key: 'ORDER_CONFIRMED', label: 'Order confirmed', completed: true },
          { key: 'PREPARING', label: 'Preparing your order', completed: true },
          { key: 'READY_TO_SHIP', label: 'Order ready', completed: true },
          { key: 'DELIVERED', label: 'Completed', completed: true },
        ],
      }),
      receivingChoice: {
        eligible: false,
        canSelect: false,
        selectedMethod: null,
        selectedMethodLabel: null,
        selectedAt: null,
      },
    });
    expect(display.headline.label).toBe('Completed');
    expect(display.fulfillment.label).toBe('Completed');
    expect(display.receiving.label).toBeNull();
    expect(display.receiving.actionRequired).toBe(false);
    expect(shouldOfferCancel({ status: 'completed', canCancel: null })).toBe(false);
  });

  it('keeps Wave 2 late payment reconciliation on cancelled orders', () => {
    const display = buildOrderLifecyclePresentation({
      status: 'cancelled',
      paymentStatus: 'paid',
      transactionStatus: 'successful',
      receivingChoice: SELF_PICKUP,
    });
    expect(display.order.label).toBe('Cancelled');
    expect(display.payment.label).toBe('Paid');
    expect(display.headline.label).toBe('Cancelled');
    expect(display.receiving.label).toBeNull();
    expect(shouldOfferCancel({ status: 'cancelled', canCancel: null })).toBe(false);
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
