import {
  fetchCustomerOrder,
} from "@/lib/api/customer-orders";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { paymentService } from "@/lib/payment/PaymentService";
import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";

function hasShippingAddress(address: ShippingAddress): boolean {
  return Boolean(
    address.addressLine1?.trim() ||
      address.city?.trim() ||
      address.region?.trim(),
  );
}

function hasCustomerDetails(customer: CustomerInformation): boolean {
  return Boolean(
    customer.firstName?.trim() ||
      customer.lastName?.trim() ||
      customer.email?.trim() ||
      customer.phone?.trim(),
  );
}

/** Prefer backend fields for payment/progress; keep checkout snapshot for display-only fields. */
export function mergeOrderSuccessWithSnapshot(
  apiOrder: Order,
  snapshot: Order | null,
): Order {
  if (!snapshot) {
    return apiOrder;
  }

  return normalizeOrder({
    ...snapshot,
    id: apiOrder.id,
    orderNumber: apiOrder.orderNumber,
    status: apiOrder.status,
    paymentStatus: apiOrder.paymentStatus,
    items: apiOrder.items,
    subtotal: apiOrder.subtotal,
    shippingTotal: apiOrder.shippingTotal,
    grandTotal: apiOrder.grandTotal,
    totals: apiOrder.totals,
    timeline: apiOrder.timeline,
    progress: apiOrder.progress ?? snapshot.progress ?? null,
    statusHistory: apiOrder.statusHistory,
    paymentMethod: apiOrder.paymentMethod ?? snapshot.paymentMethod,
    paymentReference: snapshot.paymentReference ?? apiOrder.paymentReference,
    createdAt: apiOrder.createdAt || snapshot.createdAt,
    updatedAt: apiOrder.updatedAt || snapshot.updatedAt,
    customer: hasCustomerDetails(snapshot.customer)
      ? snapshot.customer
      : apiOrder.customer,
    shippingAddress: hasShippingAddress(snapshot.shippingAddress)
      ? snapshot.shippingAddress
      : apiOrder.shippingAddress,
    orderNotes: snapshot.orderNotes || apiOrder.orderNotes,
    itemShippingBreakdown:
      snapshot.itemShippingBreakdown && snapshot.itemShippingBreakdown.length > 0
        ? snapshot.itemShippingBreakdown
        : apiOrder.itemShippingBreakdown,
    cartSnapshot: snapshot.cartSnapshot ?? apiOrder.cartSnapshot,
    shippingMethod: snapshot.shippingMethod ?? apiOrder.shippingMethod,
  });
}

export async function resolveOrderSuccessOrder(orderId: string): Promise<Order | null> {
  const trimmedOrderId = orderId.trim();
  if (!trimmedOrderId) {
    return null;
  }

  const snapshot = paymentService.resolveOrder(trimmedOrderId);

  if (!getCustomerApiToken()) {
    return snapshot;
  }

  try {
    const apiOrder = await fetchCustomerOrder(trimmedOrderId);
    return mergeOrderSuccessWithSnapshot(apiOrder, snapshot);
  } catch {
    return snapshot;
  }
}
