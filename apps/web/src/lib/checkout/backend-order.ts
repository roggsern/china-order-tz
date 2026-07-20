import { linkDraftToOrder } from "@/lib/checkout/idempotency";
import type { BackendOrderConfirmation } from "@/lib/api/customer-checkout";
import { mapBackendSummaryToTotals } from "@/lib/api/customer-checkout";
import { saveOrder } from "@/lib/payment/order-storage";
import { createInitialStatusHistory } from "@/lib/order/status-history";
import { syncTimelineWithOrder } from "@/lib/payment/timeline";
import type { CartState, CartTotals } from "@/lib/types/cart";
import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import type { ItemShippingBreakdown, Order, OrderLineItem } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import type { ShippingMethodCode } from "@/lib/shipping/types";

type SaveBackendOrderInput = {
  confirmation: BackendOrderConfirmation;
  draftId: string;
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  orderNotes: string;
  items: OrderLineItem[];
  totals: CartTotals;
  cartSnapshot: CartState;
  shippingMethod?: ShippingMethodCode | null;
  itemShippingBreakdown?: ItemShippingBreakdown[];
};

export function saveLocalOrderFromBackendConfirmation(input: SaveBackendOrderInput): Order {
  const { confirmation } = input;
  const placedAt = confirmation.order.placed_at || new Date().toISOString();
  const totals = mapBackendSummaryToTotals(confirmation.summary, input.cartSnapshot.items);

  const order: Order = {
    id: confirmation.order.id,
    orderNumber: confirmation.order.order_number,
    paymentStatus: PAYMENT_STATUS.PENDING,
    paymentMethod: null,
    paymentReference: null,
    paymentTransactionId: null,
    status: ORDER_STATUS.PENDING,
    createdAt: placedAt,
    updatedAt: placedAt,
    customer: input.customer,
    shippingAddress: input.shippingAddress,
    orderNotes: input.orderNotes,
    items: input.items,
    cartSnapshot: input.cartSnapshot,
    subtotal: totals.productTotal,
    shippingTotal: totals.shippingTotal,
    shippingMethod: input.shippingMethod ?? null,
    itemShippingBreakdown: input.itemShippingBreakdown ?? [],
    grandTotal: totals.grandTotal,
    totals,
    timeline: [],
    statusHistory: createInitialStatusHistory(placedAt),
  };

  order.timeline = syncTimelineWithOrder(order);
  linkDraftToOrder(input.draftId, order.id);
  saveOrder(order);
  // Do not clear/lock the cart here — payment has not been selected yet.
  // Cart is locked when the customer completes payment or places COD/bank transfer.

  return order;
}
