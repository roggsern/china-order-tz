import type { CartState, CartLineItem } from "@/lib/types/cart";
import type { CreateOrderInput, FinalizeOrderInput, Order, OrderLineItem, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS, normalizeOrder } from "@/lib/types/order";
import type { CorePaymentStatus, PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import { deepCopyCart, mapCartToOrderItems, buildShippingSnapshotFromOrderItems } from "@/lib/checkout/cart-snapshot";
import {
  clearCheckoutSession,
  getCheckoutSession,
  setCheckoutSession,
} from "@/lib/checkout/session";
import { calculateCartTotals } from "@/lib/cart/utils";
import { generateOrderNumber } from "@/lib/payment/order-number";
import {
  generateMpesaReference,
  reconcilePaymentStates,
  resolvePaymentOutcome,
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

function applyOrderUpdate(order: Order, patch: Partial<Order>): Order {
  const now = new Date().toISOString();
  const next: Order = {
    ...order,
    ...patch,
    updatedAt: now,
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
  };
}

/**
 * Payment orchestration layer — swap gateway logic here in Phase 4B.
 * Checkout UI and order creation flow stay unchanged when integrating Selcom etc.
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
   * Order is persisted as pending before payment runs; payment updates status via callback.
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

    if (input.paymentMethod === PAYMENT_METHOD_CODES.MPESA) {
      const paymentResult = await this.initiatePayment({
        method: input.paymentMethod,
        amount: totals.grandTotal,
        phone: input.customer.phone,
      });

      if (!paymentResult.success) {
        this.updatePaymentStatus(orderId, "failed");
        throw new Error(paymentResult.message ?? "Payment failed. Please try again.");
      }

      const paid = this.updatePaymentStatus(orderId, "paid", {
        paymentReference: paymentResult.reference,
      });

      if (!paid) {
        throw new Error("Payment succeeded but the order could not be updated.");
      }

      return paid;
    }

    const outcome = resolvePaymentOutcome(input.paymentMethod);
    const reconciled = reconcilePaymentStates({
      paymentStatus: outcome.paymentStatus,
      status: outcome.orderStatus,
      paymentMethod: input.paymentMethod,
    });
    const finalized = applyOrderUpdate(order, {
      paymentStatus: reconciled.paymentStatus,
      status: reconciled.status,
    });
    saveOrder(finalized);
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
    });
    saveOrder(reset);
    lockCartForOrderInStorage(existing.id);

    if (input.paymentMethod === PAYMENT_METHOD_CODES.MPESA) {
      const paymentResult = await this.initiatePayment({
        method: input.paymentMethod,
        amount: input.totals.grandTotal,
        phone: input.customer.phone,
      });

      if (!paymentResult.success) {
        this.updatePaymentStatus(existing.id, "failed");
        throw new Error(paymentResult.message ?? "Payment failed. Please try again.");
      }

      const paid = this.updatePaymentStatus(existing.id, "paid", {
        paymentReference: paymentResult.reference,
      });

      if (!paid) {
        throw new Error("Payment succeeded but the order could not be updated.");
      }

      return paid;
    }

    const outcome = resolvePaymentOutcome(input.paymentMethod);
    const reconciled = reconcilePaymentStates({
      paymentStatus: outcome.paymentStatus,
      status: outcome.orderStatus,
      paymentMethod: input.paymentMethod,
    });
    const finalized = applyOrderUpdate(reset, {
      paymentStatus: reconciled.paymentStatus,
      status: reconciled.status,
    });
    saveOrder(finalized);
    return this.hydrateOrder(finalized);
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
   * Gateway hook — simulates M-Pesa success until a real provider is integrated.
   * Real integrations should call handlePaymentCallback() from the webhook handler.
   */
  async initiatePayment(input: {
    method: PaymentMethodCode;
    amount: number;
    phone: string;
  }): Promise<{ success: boolean; reference: string | null; message?: string }> {
    void input.amount;
    void input.phone;

    if (input.method !== PAYMENT_METHOD_CODES.MPESA) {
      return { success: true, reference: null };
    }

    await new Promise((resolve) => setTimeout(resolve, 900));

    return {
      success: true,
      reference: generateMpesaReference(),
    };
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

      return applyOrderUpdate(order, {
        paymentStatus: reconciled.paymentStatus,
        status: reconciled.status,
        paymentReference:
          options?.paymentReference !== undefined
            ? options.paymentReference
            : order.paymentReference,
      });
    });

    if (!updated) {
      return null;
    }

    return this.hydrateOrder(updated);
  }

  /** Simulated gateway callback entry point — wire real webhooks here. */
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

      return applyOrderUpdate(order, patch);
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
