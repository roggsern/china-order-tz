import {
  CustomerOrderApiError,
  fetchCustomerOrder,
} from "@/lib/api/customer-orders";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { isAuthRequiredMessage } from "@/lib/auth/friendly-auth-messages";

export type OrderTrackingLookupResult =
  | {
      status: "found";
      order: {
        id: string;
        orderNumber: string;
      };
    }
  | { status: "not_found" }
  | { status: "needs_auth" }
  | { status: "error"; message: string };

export async function lookupCustomerOrderForTracking(
  query: string,
  options?: { authToken?: string | null },
): Promise<OrderTrackingLookupResult> {
  const trimmed = query.trim();

  if (!trimmed) {
    return {
      status: "error",
      message: "Please enter your order ID or order number.",
    };
  }

  const authToken =
    options?.authToken === undefined ? getCustomerApiToken() : options.authToken;

  if (!authToken) {
    return { status: "needs_auth" };
  }

  try {
    const order = await fetchCustomerOrder(trimmed, authToken);

    return {
      status: "found",
      order: {
        id: order.id,
        orderNumber: order.orderNumber,
      },
    };
  } catch (error) {
    if (error instanceof CustomerOrderApiError) {
      if (error.statusCode === 404) {
        return { status: "not_found" };
      }

      if (error.statusCode === 401 || isAuthRequiredMessage(error.message)) {
        return { status: "needs_auth" };
      }

      return {
        status: "error",
        message: error.message,
      };
    }

    if (error instanceof Error) {
      if (isAuthRequiredMessage(error.message)) {
        return { status: "needs_auth" };
      }

      return {
        status: "error",
        message: error.message,
      };
    }

    return {
      status: "error",
      message: "Unable to look up this order.",
    };
  }
}
