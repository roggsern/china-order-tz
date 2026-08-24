import { paymentMethodLabel } from '@/src/features/payments/utils/paymentAvailability';
import type {
  LastMileReceivingMethod,
  OrderProgress,
  OrderShipmentSummary,
  ReceivingChoiceSnapshot,
} from '../models/types';
import {
  isNegativeTerminalOrderStatus,
  isPendingReceivingPresentationKey,
  isSuccessTerminalLifecycle,
  isSuccessTerminalOrderStatus,
  shouldOfferReceivingChoice,
} from './orderLifecycleRules';

const ORDER_DISPLAY_LABELS: Record<string, string> = {
  pending: 'Awaiting payment',
  pending_payment: 'Awaiting payment',
  paid: 'Paid',
  confirmed: 'Paid',
  processing: 'Processing',
  shipped: 'Shipped',
  delivered: 'Delivered',
  completed: 'Completed',
  cancelled: 'Cancelled',
  refund_pending: 'Refund in progress',
  refunded: 'Refunded',
};

const PENDING_PAYMENT_STATUSES = new Set([
  'pending',
  'initiated',
  'processing',
  'pending_payment',
]);

const PAID_PAYMENT_STATUSES = new Set(['paid', 'successful', 'confirmed']);

const AWAITING_FULFILLMENT_KEYS = new Set(['AWAITING_PAYMENT', 'ORDER_CONFIRMED']);

const STARTED_FULFILLMENT_KEYS = new Set([
  'PREPARING',
  'READY_TO_SHIP',
  'SHIPPED',
  'ARRIVED_TANZANIA',
  'CHOOSE_RECEIVING_METHOD',
  'SENT_TO_AGENT',
  'DELIVERED_TO_AGENT',
  'DELIVERED',
]);

const TERMINAL_PROGRESS_KEYS = new Set([
  'CANCELLED',
  'REFUND_PENDING',
  'REFUNDED',
]);

export type OrderDisplayStatus = {
  key: string;
  label: string;
};

export type PaymentDisplayStatus = {
  key: string;
  label: string;
  methodLabel: string | null;
};

export type FulfillmentDisplayStatus = {
  key: string;
  label: string;
  isActive: boolean;
  showProgression: boolean;
};

export type ReceivingDisplayStatus = {
  key: string;
  label: string | null;
  actionRequired: boolean;
  selectedMethod: LastMileReceivingMethod | null;
};

export type OrderLifecyclePresentation = {
  order: OrderDisplayStatus;
  payment: PaymentDisplayStatus;
  fulfillment: FulfillmentDisplayStatus;
  receiving: ReceivingDisplayStatus;
  /** Customer-facing badge. Does not change backend order.status. */
  headline: OrderDisplayStatus;
};

export type OrderLifecycleInput = {
  status: string | null;
  statusLabel?: string | null;
  paymentStatus?: string | null;
  paymentMethod?: string | null;
  paymentProvider?: string | null;
  transactionStatus?: string | null;
  progress?: OrderProgress | null;
  shipment?: Pick<OrderShipmentSummary, 'status' | 'statusLabel'> | null;
  receivingChoice?: ReceivingChoiceSnapshot | null;
};

function normalize(value: string | null | undefined): string {
  return value?.trim().toLowerCase() ?? '';
}

/** Negative terminal only (cancelled / refunded / refund_pending). */
export function isTerminalOrderStatus(status: string | null | undefined): boolean {
  return isNegativeTerminalOrderStatus(status);
}

/**
 * Order-layer label from backend order.status.
 * Terminal statuses always win over stale status_label or payment txn state.
 */
export function resolveOrderDisplayStatus(input: {
  status: string | null;
  statusLabel?: string | null;
}): OrderDisplayStatus {
  const status = normalize(input.status);

  if (status === 'cancelled') {
    return { key: 'cancelled', label: 'Cancelled' };
  }
  if (status === 'refunded') {
    return { key: 'refunded', label: 'Refunded' };
  }
  if (status === 'refund_pending') {
    return { key: 'refund_pending', label: 'Refund in progress' };
  }

  const known = ORDER_DISPLAY_LABELS[status];
  if (known) {
    return { key: status, label: known };
  }

  const fallback = input.statusLabel?.trim();
  if (fallback) {
    return { key: status || 'unknown', label: fallback };
  }
  if (status) {
    return { key: status, label: status.replace(/_/g, ' ') };
  }
  return { key: 'unknown', label: 'Status unavailable' };
}

export function resolvePaymentMethodDisplayLabel(
  method?: string | null,
  provider?: string | null,
): string | null {
  const code = (method ?? provider ?? '').trim().toLowerCase();
  if (!code) return null;
  if (code === 'cod') {
    return paymentMethodLabel('cash');
  }
  return paymentMethodLabel(code);
}

/**
 * Payment-layer label from customer payment_status.
 * Stale processing txns cannot reopen a cancelled/refunded order.
 * A late successful txn on a cancelled order may show Paid for reconciliation.
 */
export function resolvePaymentDisplayStatus(input: {
  orderStatus: string | null;
  paymentStatus?: string | null;
  transactionStatus?: string | null;
  paymentMethod?: string | null;
  paymentProvider?: string | null;
}): PaymentDisplayStatus {
  const orderStatus = normalize(input.orderStatus);
  const paymentStatus = normalize(input.paymentStatus);
  const transactionStatus = normalize(input.transactionStatus);
  const methodLabel = resolvePaymentMethodDisplayLabel(
    input.paymentMethod,
    input.paymentProvider,
  );

  if (orderStatus === 'refunded' || paymentStatus === 'refunded') {
    return { key: 'refunded', label: 'Refunded', methodLabel };
  }

  if (orderStatus === 'cancelled') {
    if (PAID_PAYMENT_STATUSES.has(paymentStatus)) {
      return { key: 'paid', label: 'Paid', methodLabel };
    }
    return { key: 'not_completed', label: 'Payment not completed', methodLabel };
  }

  if (PAID_PAYMENT_STATUSES.has(paymentStatus)) {
    return { key: 'paid', label: 'Paid', methodLabel };
  }

  if (paymentStatus === 'failed' || paymentStatus === 'expired') {
    return { key: 'failed', label: 'Payment not completed', methodLabel };
  }

  if (paymentStatus === 'cancelled') {
    return { key: 'not_completed', label: 'Payment not completed', methodLabel };
  }

  if (
    PENDING_PAYMENT_STATUSES.has(paymentStatus) ||
    transactionStatus === 'pending' ||
    transactionStatus === 'processing' ||
    orderStatus === 'pending' ||
    orderStatus === 'pending_payment'
  ) {
    return { key: 'pending', label: 'Awaiting payment', methodLabel };
  }

  if (paymentStatus) {
    return {
      key: paymentStatus,
      label: paymentStatus.replace(/_/g, ' '),
      methodLabel,
    };
  }

  return { key: 'unknown', label: 'Awaiting payment', methodLabel };
}

/**
 * Fulfillment-layer label from backend progress/shipment — never from order.status alone.
 */
export function resolveFulfillmentDisplayStatus(input: {
  orderStatus: string | null;
  progress?: OrderProgress | null;
  shipment?: Pick<OrderShipmentSummary, 'status' | 'statusLabel'> | null;
}): FulfillmentDisplayStatus {
  const orderStatus = normalize(input.orderStatus);
  const progress = input.progress ?? null;
  const currentKey = progress?.currentKey?.trim() ?? '';

  if (isNegativeTerminalOrderStatus(orderStatus)) {
    if (currentKey && TERMINAL_PROGRESS_KEYS.has(currentKey) && progress?.currentLabel) {
      return {
        key: currentKey,
        label: progress.currentLabel,
        isActive: false,
        showProgression: false,
      };
    }
    return {
      key: orderStatus,
      label: 'Not started',
      isActive: false,
      showProgression: false,
    };
  }

  if (
    orderStatus === 'pending' ||
    orderStatus === 'pending_payment' ||
    !currentKey ||
    AWAITING_FULFILLMENT_KEYS.has(currentKey) ||
    !STARTED_FULFILLMENT_KEYS.has(currentKey)
  ) {
    return {
      key: 'not_started',
      label: 'Not started',
      isActive: false,
      showProgression: false,
    };
  }

  const label = progress?.currentLabel?.trim() || currentKey.replace(/_/g, ' ');
  return {
    key: currentKey,
    label,
    isActive: currentKey !== 'DELIVERED',
    showProgression: true,
  };
}

/**
 * Receiving-choice presentation from the backend snapshot.
 * Historical selected_method is preserved; pending pickup/delivery copy
 * cannot override authoritative delivered/completed progress.
 */
export function resolveReceivingDisplayStatus(input: {
  orderStatus: string | null;
  receivingChoice?: ReceivingChoiceSnapshot | null;
  progress?: OrderProgress | null;
}): ReceivingDisplayStatus {
  if (isNegativeTerminalOrderStatus(input.orderStatus)) {
    return {
      key: 'none',
      label: null,
      actionRequired: false,
      selectedMethod: null,
    };
  }

  const choice = input.receivingChoice ?? null;
  const selectedMethod = choice?.selectedMethod ?? null;
  const successTerminal = isSuccessTerminalLifecycle({
    orderStatus: input.orderStatus,
    progress: input.progress,
  });

  if (successTerminal) {
    if (selectedMethod) {
      const label = input.progress?.currentLabel?.trim() || 'Completed';
      return {
        key: 'completed',
        label,
        actionRequired: false,
        selectedMethod,
      };
    }
    return {
      key: 'none',
      label: null,
      actionRequired: false,
      selectedMethod: null,
    };
  }

  if (selectedMethod === 'self_pickup') {
    return {
      key: 'self_pickup',
      label: 'Waiting for pickup',
      actionRequired: false,
      selectedMethod: 'self_pickup',
    };
  }
  if (selectedMethod === 'negotiated_delivery') {
    return {
      key: 'negotiated_delivery',
      label: 'Delivery arrangement pending',
      actionRequired: false,
      selectedMethod: 'negotiated_delivery',
    };
  }
  if (shouldOfferReceivingChoice(choice)) {
    return {
      key: 'choice_required',
      label: 'Action required',
      actionRequired: true,
      selectedMethod: null,
    };
  }

  return {
    key: 'none',
    label: null,
    actionRequired: false,
    selectedMethod: null,
  };
}

export function resolveCustomerFacingHeadline(
  order: OrderDisplayStatus,
  receiving: ReceivingDisplayStatus,
  fulfillment?: FulfillmentDisplayStatus,
  input?: Pick<OrderLifecycleInput, 'status' | 'progress'>,
): OrderDisplayStatus {
  if (
    receiving.label &&
    (receiving.actionRequired || isPendingReceivingPresentationKey(receiving.key))
  ) {
    return { key: receiving.key, label: receiving.label };
  }

  if (
    input &&
    isSuccessTerminalLifecycle({
      orderStatus: input.status,
      progress: input.progress,
    }) &&
    !isSuccessTerminalOrderStatus(input.status) &&
    fulfillment?.label
  ) {
    return { key: fulfillment.key, label: fulfillment.label };
  }

  return order;
}

export function buildOrderLifecyclePresentation(
  input: OrderLifecycleInput,
): OrderLifecyclePresentation {
  const order = resolveOrderDisplayStatus({
    status: input.status,
    statusLabel: input.statusLabel,
  });
  const fulfillment = resolveFulfillmentDisplayStatus({
    orderStatus: input.status,
    progress: input.progress,
    shipment: input.shipment,
  });
  const receiving = resolveReceivingDisplayStatus({
    orderStatus: input.status,
    receivingChoice: input.receivingChoice,
    progress: input.progress,
  });
  return {
    order,
    payment: resolvePaymentDisplayStatus({
      orderStatus: input.status,
      paymentStatus: input.paymentStatus,
      transactionStatus: input.transactionStatus,
      paymentMethod: input.paymentMethod,
      paymentProvider: input.paymentProvider,
    }),
    fulfillment,
    receiving,
    headline: resolveCustomerFacingHeadline(order, receiving, fulfillment, input),
  };
}

/** Timeline shown to customers — hide fake shipping steps on terminal / unpaid orders. */
export function resolveProgressForDisplay(
  orderStatus: string | null,
  progress: OrderProgress | null,
): OrderProgress | null {
  const fulfillment = resolveFulfillmentDisplayStatus({ orderStatus, progress });
  if (fulfillment.showProgression) {
    return progress;
  }

  if (isNegativeTerminalOrderStatus(orderStatus) && progress?.currentKey && TERMINAL_PROGRESS_KEYS.has(progress.currentKey)) {
    return {
      currentKey: progress.currentKey,
      currentLabel: progress.currentLabel,
      steps: progress.steps.filter((step) => step.key === progress.currentKey),
    };
  }

  return null;
}

export function resolveTrackingHeroLabel(input: {
  orderStatus: string | null;
  trackingCurrentLabel?: string | null;
  trackingCurrentStatus?: string | null;
  progress?: OrderProgress | null;
  receivingChoice?: ReceivingChoiceSnapshot | null;
}): string {
  const lifecycle = buildOrderLifecyclePresentation({
    status: input.orderStatus,
    progress: input.progress,
    receivingChoice: input.receivingChoice,
  });

  if (isNegativeTerminalOrderStatus(input.orderStatus)) {
    return lifecycle.order.label;
  }

  if (
    isSuccessTerminalLifecycle({
      orderStatus: input.orderStatus,
      progress: input.progress,
    })
  ) {
    return lifecycle.headline.label;
  }

  if (
    lifecycle.receiving.actionRequired ||
    isPendingReceivingPresentationKey(lifecycle.receiving.key)
  ) {
    return lifecycle.headline.label;
  }

  if (!lifecycle.fulfillment.showProgression) {
    return lifecycle.order.label;
  }

  return (
    input.trackingCurrentLabel?.trim() ||
    input.trackingCurrentStatus?.trim() ||
    lifecycle.fulfillment.label
  );
}

export function orderDisplayTone(
  key: string,
): 'success' | 'error' | 'warning' | 'info' | 'neutral' {
  switch (key) {
    case 'cancelled':
    case 'refunded':
    case 'failed':
    case 'not_completed':
      return 'error';
    case 'refund_pending':
    case 'pending':
    case 'awaiting_payment':
    case 'pending_payment':
      return 'warning';
    case 'paid':
    case 'delivered':
    case 'completed':
    case 'DELIVERED':
    case 'DELIVERED_TO_AGENT':
    case 'refunded_complete':
      return 'success';
    case 'processing':
    case 'shipped':
    case 'PREPARING':
    case 'READY_TO_SHIP':
    case 'SHIPPED':
    case 'ARRIVED_TANZANIA':
    case 'self_pickup':
    case 'negotiated_delivery':
      return 'info';
    case 'choice_required':
      return 'warning';
    default:
      return 'neutral';
  }
}
