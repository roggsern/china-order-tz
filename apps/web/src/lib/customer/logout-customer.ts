import {
  clearCustomerApiToken,
  getCustomerApiToken,
} from "@/lib/api/customer-auth";
import { queueCustomerToast } from "@/lib/customer/customer-toast";
import { clearCustomerSession } from "@/lib/customer/session";

export type CustomerLogoutResult = {
  serverRevokeAttempted: boolean;
  serverRevokeOk: boolean;
  localCleared: true;
};

/**
 * End-to-end customer logout:
 * 1) revoke current Sanctum PAT via BFF when a token is present
 * 2) always clear local auth state (handles network failure / expired token)
 */
export async function logoutCustomer(options?: {
  showToast?: boolean;
}): Promise<CustomerLogoutResult> {
  const showToast = options?.showToast ?? true;
  const token = getCustomerApiToken();
  let serverRevokeAttempted = false;
  let serverRevokeOk = false;

  if (token) {
    serverRevokeAttempted = true;
    try {
      const response = await fetch("/api/customer/logout", {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        cache: "no-store",
      });
      serverRevokeOk = response.ok;
    } catch {
      serverRevokeOk = false;
    }
  }

  clearCustomerSession();
  clearCustomerApiToken();

  if (showToast) {
    queueCustomerToast("👋 See you again soon!");
  }

  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event("customer-session-updated"));
  }

  return {
    serverRevokeAttempted,
    serverRevokeOk,
    localCleared: true,
  };
}
