import { fetchCustomerOrder } from "@/lib/api/customer-orders";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  fetchCustomerOrderTracking,
  type CustomerTrackingPayload,
} from "@/lib/api/customer-tracking";
import type { Order } from "@/lib/types/order";

export type LiveOrderTrackingLoadResult = {
  order: Order | null;
  tracking: CustomerTrackingPayload | null;
  needsAuth: boolean;
};

export async function loadLiveOrderTracking(
  orderId: string,
  options?: { authToken?: string | null },
): Promise<LiveOrderTrackingLoadResult> {
  const authToken =
    options?.authToken === undefined ? getCustomerApiToken() : options.authToken;

  if (!authToken) {
    return {
      order: null,
      tracking: null,
      needsAuth: true,
    };
  }

  let order: Order | null = null;

  try {
    order = await fetchCustomerOrder(orderId, authToken);
  } catch {
    return {
      order: null,
      tracking: null,
      needsAuth: false,
    };
  }

  let tracking: CustomerTrackingPayload | null = null;

  try {
    tracking = await fetchCustomerOrderTracking(order.orderNumber, authToken);
  } catch {
    tracking = null;
  }

  return {
    order,
    tracking,
    needsAuth: false,
  };
}
