import { clearCustomerApiToken } from "@/lib/api/customer-auth";
import { queueCustomerToast } from "@/lib/customer/customer-toast";
import { clearCustomerSession } from "@/lib/customer/session";

export function logoutCustomer(): void {
  clearCustomerSession();
  clearCustomerApiToken();
  queueCustomerToast("👋 See you again soon!");
  window.dispatchEvent(new Event("customer-session-updated"));
}
