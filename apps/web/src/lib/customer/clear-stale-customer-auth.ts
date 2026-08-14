import { clearCustomerApiToken } from "@/lib/api/customer-auth";
import { clearCustomerSession } from "@/lib/customer/session";

/**
 * Local-only recovery when a Bearer token is rejected (401).
 * Does not attempt server revoke — the credential is already invalid.
 */
export function clearStaleCustomerAuth(): void {
  clearCustomerSession();
  clearCustomerApiToken();

  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event("customer-session-updated"));
  }
}
