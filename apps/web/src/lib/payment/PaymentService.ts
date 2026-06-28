import type { CartState, CartLineItem } from "@/lib/types/cart";
import type { CreateOrderInput, FinalizeOrderInput, Order, OrderLineItem, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS, normalizeOrder } from "@/lib/types/order";
import type { CorePaymentStatus, PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { deepCopyCart, mapCartToOrderItems, buildShippingSnapshotFromOrderItems } from "@/lib/checkout/cart-snapshot";
import {
  clearCheckoutSession,
  getCheckoutSession,
  setCheckoutSession,
} from "@/lib/checkout/session";
import { calculateCartTotals } from "@/lib/cart/utils";
import { generateOrderNumber } from "@/lib/payment/order-number";
import {
  reconcilePaymentStates,
  resolvePaymentOutcome,
  isGatewayPaymentMethod,
} from "@/lib/payment/payment-outcome";
import {
  getOrderIdForDraft,
  linkDraftToOrder,
} from "@/lib/checkout/idempotency";
import {
  getAllOrders,
  getOrderById as getStoredOrderById,
  getOrderByNumber,
  resolveOrderLookup,
  saveOrder,
  updateOrder,
  updateOrderById,
} from "@/lib/payment/order-storage";
import { syncTimelineWithOrder } from "@/lib/payment/timeline";
import { lockCartForOrderInStorage } from "@/lib/order/cart-lock";
import { initiatePaymentRequest, simulatePaymentRequest, verifyPaymentRequest } from "@/lib/payment/client-api";
import type { InitiateStkPushResult, SimulateStkPushResult, VerifyPaymentResult } from "@/lib/payments/types";
import { logPaymentEvent } from "@/lib/payment/payment-logger";
import {
  applyOrderStatusHistoryPatch,
  createInitialStatusHistory,
} from "@/lib/order/status-history";

function applyOrderUpdate(
  order: Order,
  patch: Partial<Order>,
  updatedBy: "system" | "admin" = "system",
): Order {
  const withHistory = applyOrderStatusHistoryPatch(order, patch, updatedBy);
  const next: Order = {
    ...withHistory,
    ...patch,
    updatedAt: withHistory.updatedAt,
    statusHistory: withHistory.statusHistory,
  };
  next.timeline = syncTimelineWithOrder(next);
  return next;
}

function emptyCustomerFields(): Order["customer"] {
  return { firstName: "", lastName: "", email: "", phone: "" };
}

function emptyAddressFields(): Order["shippingAddress"] {
  return {
    addressLine1: "",
    addressLine2: "",
    city: "",
    region: "",
    postalCode: "",
    country: "Tanzania",
  };
}

function buildPendingOrder(input: {
  id: string;
  orderNumber: string;
  paymentMethod: PaymentMethodCode;
  customer: CreateOrderInput["customer"];
  shippingAddress: CreateOrderInput["shippingAddress"];
  orderNotes: string;
  items: OrderLineItem[];
  totals: CreateOrderInput["totals"];
  cartSnapshot?: CartState;
  shippingMethod: Order["shippingMethod"];
  itemShippingBreakdown: Order["itemShippingBreakdown"];
}): Order {
  const now = new Date().toISOString();
  const { totals } = input;

  return {
    id: input.id,
    orderNumber: input.orderNumber,
    paymentStatus: PAYMENT_STATUS.PENDING,
    paymentMethod: input.paymentMethod,
    paymentReference: null,
    paymentTransactionId: null,
    status: ORDER_STATUS.PENDING,
    createdAt: now,
    updatedAt: now,
    customer: input.customer,
    shippingAddress: input.shippingAddress,
    orderNotes: input.orderNotes,
    items: input.items,
    cartSnapshot: input.cartSnapshot ?? {
      items: [],
      savedForLater: [],
      discount: totals.discount,
    },
    subtotal: totals.productTotal,
    shippingTotal: totals.shippingTotal,
    shippingMethod: input.shippingMethod,
    itemShippingBreakdown: input.itemShippingBreakdown ?? [],
    grandTotal: totals.grandTotal,
    totals,
    timeline: [],
    statusHistory: createInitialStatusHistory(now),
  };
}

function finalizeNonMpesaOrder(order: Order, paymentMethod: PaymentMethodCode): Order {
  const outcome = resolvePaymentOutcome(paymentMethod);
  const reconciled = reconcilePaymentStates({
    paymentStatus: outcome.paymentStatus,
    status: outcome.orderStatus,
    paymentMethod,
  });
  const finalized = applyOrderUpdate(order, {
    paymentStatus: reconciled.paymentStatus,
    status: reconciled.status,
  });
  saveOrder(finalized);
  return finalized;
}

/**
 * Payment orchestration layer — M-Pesa STK Push runs server-side via /api/payments/*.
 * Checkout UI calls createOrder() then initiatePayment(order) for M-Pesa.
 */
export class PaymentService {
  /**
   * @deprecated Do not use — orders are created only on the payment step via createOrder().
   */
  initiateCheckout(cartState: CartState): Order {
    const cartSnapshot = deepCopyCart(cartState);
    const totals = calculateCartTotals(cartSnapshot);
    const now = new Date().toISOString();
    const orderNumber = generateOrderNumber();
    const id = crypto.randomUUID();

    const items = mapCartToOrderItems(cartSnapshot.items);
    const shippingSnapshot = buildShippingSnapshotFromOrderItems(items);

    const order: Order = {
      id,
      orderNumber,
      paymentStatus: PAYMENT_STATUS.PENDING,
      paymentMethod: null,
      paymentReference: null,
      paymentTransactionId: null,
      status: ORDER_STATUS.PENDING,
      createdAt: now,
      updatedAt: now,
      customer: emptyCustomerFields(),
      shippingAddress: emptyAddressFields(),
      orderNotes: "",
      items,
      cartSnapshot,
      subtotal: totals.productTotal,
      shippingTotal: totals.shippingTotal,
      shippingMethod: shippingSnapshot.shippingMethod,
      itemShippingBreakdown: shippingSnapshot.itemShippingBreakdown,
      grandTotal: totals.grandTotal,
      totals,
      timeline: [],
    };

    order.timeline = syncTimelineWithOrder(order);
    saveOrder(order);
    setCheckoutSession({ orderId: id, orderNumber });
    return order;
  }

  /**
   * @deprecated Orders are finalized via createOrder() on the payment step.
   */
  async finalizeOrder(orderNumber: string, input: FinalizeOrderInput): Promise<Order> {
    const existing = getOrderByNumber(orderNumber);
    if (!existing) {
      throw new Error(`Order ${orderNumber} not found`);
    }

    const finalized = applyOrderUpdate(existing, {
      customer: input.customer,
      shippingAddress: input.shippingAddress,
      orderNotes: input.orderNotes,
      paymentMethod: input.paymentMethod,
      paymentStatus: PAYMENT_STATUS.PENDING,
      status: ORDER_STATUS.PENDING,
    });

    saveOrder(finalized);
    clearCheckoutSession();
    return finalized;
  }

  /**
   * Creates an order exactly once per idempotency key (checkout draft).
   * M-Pesa orders stay pending until initiatePayment() + verifyPayment() confirm payment.
   */
  async createOrder(input: CreateOrderInput): Promise<Order> {
    if (!input.paymentMethod) {
      throw new Error("Payment method is required.");
    }

    if (input.items.length === 0) {
      throw new Error("Cannot place an order with an empty cart.");
    }

    if (input.idempotencyKey) {
      const existingOrderId = getOrderIdForDraft(input.idempotencyKey);
      if (existingOrderId) {
        const existing = getStoredOrderById(existingOrderId);
        if (existing) {
          if (existing.paymentStatus === PAYMENT_STATUS.FAILED) {
            return this.retryFailedOrderPayment(existing, input);
          }
          return this.hydrateOrder(existing);
        }
      }
    }

    const totals = input.totals;
    const shippingSnapshot =
      input.itemShippingBreakdown && input.itemShippingBreakdown.length > 0
        ? {
            shippingTotal: totals.shippingTotal,
            shippingMethod:
              input.shippingMethod ?? buildShippingSnapshotFromOrderItems(input.items).shippingMethod,
            itemShippingBreakdown: input.itemShippingBreakdown,
          }
        : buildShippingSnapshotFromOrderItems(input.items);

    const orderId = crypto.randomUUID();
    const orderNumber = generateOrderNumber();

    if (input.idempotencyKey) {
      linkDraftToOrder(input.idempotencyKey, orderId);
    }

    const order = buildPendingOrder({
      id: orderId,
      orderNumber,
      paymentMethod: input.paymentMethod,
      customer: input.customer,
      shippingAddress: input.shippingAddress,
      orderNotes: input.orderNotes,
      items: input.items,
      totals,
      cartSnapshot: input.cartSnapshot,
      shippingMethod: shippingSnapshot.shippingMethod,
      itemShippingBreakdown: shippingSnapshot.itemShippingBreakdown,
    });

    order.timeline = syncTimelineWithOrder(order);
    saveOrder(order);
    lockCartForOrderInStorage(orderId);

    if (isGatewayPaymentMethod(input.paymentMethod)) {
      return this.hydrateOrder(order);
    }

    const finalized = finalizeNonMpesaOrder(order, input.paymentMethod);
    return this.hydrateOrder(finalized);
  }

  private async retryFailedOrderPayment(
    existing: Order,
    input: CreateOrderInput,
  ): Promise<Order> {
    const reset = applyOrderUpdate(existing, {
      paymentMethod: input.paymentMethod,
      paymentStatus: PAYMENT_STATUS.PENDING,
      status: ORDER_STATUS.PENDING,
      paymentReference: null,
      paymentTransactionId: null,
    });
    saveOrder(reset);
    lockCartForOrderInStorage(existing.id);

    if (isGatewayPaymentMethod(input.paymentMethod)) {
      return this.hydrateOrder(reset);
    }

    const finalized = finalizeNonMpesaOrder(reset, input.paymentMethod);
    return this.hydrateOrder(finalized);
  }

  /**
   * Initiates M-Pesa STK Push for a pending order via the server payment gateway.
   */
  async initiatePayment(order: Order): Promise<InitiateStkPushResult> {
    if (!isGatewayPaymentMethod(order.paymentMethod)) {
      return {
        success: true,
        transactionId: null,
        status: "paid",
        checkoutRequestId: null,
        message: "No gateway initiation required for this payment method.",
        mode: "test",
      };
    }

    const result = await initiatePaymentRequest(order);

    logPaymentEvent("initiate:complete", {
      orderId: order.id,
      transactionId: result.transactionId,
      status: result.status,
      mode: result.mode,
    });

    if (result.transactionId) {
      updateOrderById(order.id, (entry) =>
        applyOrderUpdate(entry, { paymentTransactionId: result.transactionId }),
      );
    }

    if (result.status === "failed") {
      this.handlePaymentCallback(order.id, "failed", null);
    }

    return result;
  }

  /**
   * Polls payment status and syncs the local order when the gateway confirms payment.
   */
  async verifyPayment(transactionId: string): Promise<VerifyPaymentResult> {
    const result = await verifyPaymentRequest(transactionId);

    logPaymentEvent("verify:poll", {
      transactionId,
      status: result.status,
      orderId: result.orderId,
    });

    if (result.status === "paid") {
      logPaymentEvent("verify:paid", {
        transactionId,
        paymentReference: result.paymentReference,
      });
      this.handlePaymentCallback(result.orderId, "paid", result.paymentReference);
    } else if (result.status === "failed") {
      logPaymentEvent("verify:failed", { transactionId, message: result.message });
      this.handlePaymentCallback(result.orderId, "failed", null);
    }

    return result;
  }

  /**
   * Starts STK-style payment: order fulfillment → Processing, payment stays pending,
   * gateway transaction created for polling on the processing page.
   */
  async beginStkPaymentProcessing(
    order: Order,
  ): Promise<{ order: Order; transactionId: string }> {
    logPaymentEvent("stk:begin", {
      orderId: order.id,
      orderNumber: order.orderNumber,
    });

    const processing = this.updateOrderStatus(order.orderNumber, ORDER_STATUS.PROCESSING);
    const working = processing ?? order;

    const result = await this.initiatePayment(working);

    if (!result.success || !result.transactionId) {
      logPaymentEvent("stk:failed", {
        orderId: order.id,
        message: result.message,
      });
      throw new Error(result.message ?? "Payment could not be initiated.");
    }

    logPaymentEvent("stk:initiated", {
      orderId: order.id,
      transactionId: result.transactionId,
      mode: result.mode,
    });

    const latest = this.getOrderById(order.id);
    if (!latest) {
      throw new Error("Order could not be loaded after STK initiation.");
    }

    return { order: latest, transactionId: result.transactionId };
  }

  /**
   * @deprecated Use beginStkPaymentProcessing + processing page polling for realistic STK UX.
   */
  async simulatePayment(order: Order): Promise<Order> {
    logPaymentEvent("simulate:start", {
      orderId: order.id,
      orderNumber: order.orderNumber,
      amount: order.totals.grandTotal,
    });

    let result: SimulateStkPushResult;

    try {
      result = await simulatePaymentRequest(order);
      logPaymentEvent("simulate:server", {
        transactionId: result.transactionId,
        paymentReference: result.paymentReference,
      });
    } catch (error) {
      logPaymentEvent("simulate:failed", {
        orderId: order.id,
        message: error instanceof Error ? error.message : "Simulate failed",
      });
      throw error;
    }

    const paid = this.handlePaymentCallback(order.id, "paid", result.paymentReference);
    if (!paid) {
      logPaymentEvent("simulate:failed", { orderId: order.id, reason: "order_update_failed" });
      throw new Error("Simulated payment succeeded but the order could not be updated.");
    }

    logPaymentEvent("simulate:paid", {
      orderId: order.id,
      paymentReference: result.paymentReference,
      transactionId: result.transactionId,
    });

    updateOrderById(order.id, (entry) =>
      applyOrderUpdate(entry, { paymentTransactionId: result.transactionId }),
    );

    const processing = this.updateOrderStatus(paid.orderNumber, ORDER_STATUS.PROCESSING);
    if (!processing) {
      logPaymentEvent("simulate:failed", { orderId: order.id, reason: "status_update_failed" });
      throw new Error("Payment recorded but order status could not be advanced.");
    }

    logPaymentEvent("simulate:processing", {
      orderId: order.id,
      orderStatus: ORDER_STATUS.PROCESSING,
    });

    logPaymentEvent("simulate:complete", {
      orderId: order.id,
      orderNumber: order.orderNumber,
      transactionId: result.transactionId,
      paymentReference: result.paymentReference,
    });

    return processing;
  }

  getActiveCheckoutOrder(): Order | null {
    const session = getCheckoutSession();
    if (!session) {
      return null;
    }

    const byId = getStoredOrderById(session.orderId);
    if (byId) {
      return this.hydrateOrder(byId);
    }

    return this.getOrder(session.orderNumber);
  }

  /**
   * Update payment status by order ID — used by gateway callbacks and admin tools.
   */
  updatePaymentStatus(
    orderId: string,
    paymentStatus: CorePaymentStatus,
    options?: { paymentReference?: string | null },
  ): Order | null {
    const statusMap: Record<CorePaymentStatus, PaymentStatus> = {
      pending: PAYMENT_STATUS.PENDING,
      paid: PAYMENT_STATUS.PAID,
      failed: PAYMENT_STATUS.FAILED,
    };

    const mappedStatus = statusMap[paymentStatus];

    const updated = updateOrderById(orderId, (order) => {
      const reconciled = reconcilePaymentStates({
        paymentStatus: mappedStatus,
        status: order.status,
        paymentMethod: order.paymentMethod,
      });

      const nextStatus =
        mappedStatus === PAYMENT_STATUS.PAID && order.status === ORDER_STATUS.PROCESSING
          ? ORDER_STATUS.PROCESSING
          : reconciled.status;

      return applyOrderUpdate(order, {
        paymentStatus: reconciled.paymentStatus,
        status: nextStatus,
        paymentReference:
          options?.paymentReference !== undefined
            ? options.paymentReference
            : order.paymentReference,
        paymentTransactionId:
          paymentStatus === "paid" || paymentStatus === "failed" ? null : order.paymentTransactionId,
      });
    });

    if (!updated) {
      return null;
    }

    return this.hydrateOrder(updated);
  }

  /** Gateway callback entry point — also used after verifyPayment syncs status. */
  handlePaymentCallback(
    orderId: string,
    outcome: "paid" | "failed",
    paymentReference?: string | null,
  ): Order | null {
    return this.updatePaymentStatus(orderId, outcome, { paymentReference });
  }

  getOrder(orderNumber: string): Order | null {
    const order = getOrderByNumber(orderNumber);
    if (!order) {
      return null;
    }

    return this.hydrateOrder(order);
  }

  getOrderById(orderId: string): Order | null {
    const order = getStoredOrderById(orderId);
    if (!order) {
      return null;
    }

    return this.hydrateOrder(order);
  }

  /** Resolves an order by UUID or order number (case-insensitive). */
  resolveOrder(query: string): Order | null {
    const order = resolveOrderLookup(query);
    if (!order) {
      return null;
    }

    return this.hydrateOrder(order);
  }

  listOrders(): Order[] {
    return getAllOrders().map((order) => this.hydrateOrder(order));
  }

  markPaymentReceived(orderNumber: string): Order | null {
    const order = getOrderByNumber(orderNumber);
    if (!order) {
      return null;
    }

    return this.updatePaymentStatus(order.id, "paid");
  }

  updateOrderStatus(orderNumber: string, status: OrderStatus): Order | null {
    return updateOrder(orderNumber, (order) => {
      const patch: Partial<Order> = { status };

      if (status === ORDER_STATUS.CANCELLED) {
        patch.paymentStatus =
          order.paymentStatus === PAYMENT_STATUS.PAID
            ? PAYMENT_STATUS.REFUNDED
            : PAYMENT_STATUS.CANCELLED;
      }

      return applyOrderUpdate(order, patch, "admin");
    });
  }

  mapCartItems(items: CartLineItem[]): OrderLineItem[] {
    return mapCartToOrderItems(items);
  }

  private hydrateOrder(order: Order): Order {
    const normalized = normalizeOrder(order);
    const reconciled = reconcilePaymentStates({
      paymentStatus: normalized.paymentStatus,
      status: normalized.status,
      paymentMethod: normalized.paymentMethod,
    });
    const hydrated: Order = {
      ...normalized,
      paymentStatus: reconciled.paymentStatus,
      status: reconciled.status,
    };
    return {
      ...hydrated,
      timeline: syncTimelineWithOrder(hydrated),
    };
  }
}

export const paymentService = new PaymentService();

export type { PaymentStatus };
