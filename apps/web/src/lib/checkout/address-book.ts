import type { CustomerAddress } from "@/lib/api/customer-addresses";
import { mapCustomerAddressToShipping, pickDefaultCustomerAddress } from "@/lib/api/customer-addresses";
import type { CustomerProfile } from "@/lib/api/customer-profile";
import type { CustomerSession } from "@/lib/customer/session";
import type { CheckoutFormData, CustomerInformation } from "@/lib/types/checkout";
import { splitFullName } from "@/lib/checkout/validation";

export const CHECKOUT_DELIVERY_ADDRESS_REQUIRED =
  "Please add a delivery address before continuing";

export function isCheckoutDeliveryAddressReady(
  selectedAddressId: string | null,
  addresses: Array<Pick<CustomerAddress, "id">>,
): boolean {
  if (!selectedAddressId?.trim()) {
    return false;
  }

  return addresses.some((row) => row.id === selectedAddressId);
}

export function resolveInitialCheckoutAddressSelection(
  addresses: CustomerAddress[],
  defaultId: string | null,
  savedSelectionId?: string | null,
): string | null {
  if (savedSelectionId && addresses.some((row) => row.id === savedSelectionId)) {
    return savedSelectionId;
  }

  const picked = pickDefaultCustomerAddress(addresses, defaultId);
  return picked?.id ?? null;
}

export function mergeProfileIntoCheckoutCustomer(
  current: CustomerInformation,
  profile: CustomerProfile | null,
  session: CustomerSession | null,
): CustomerInformation {
  const sessionName = session?.name?.trim() ?? "";
  const sessionParts = sessionName ? splitFullName(sessionName) : null;

  const firstName =
    current.firstName.trim() ||
    profile?.first_name?.trim() ||
    sessionParts?.firstName ||
    "";
  const lastName =
    current.lastName.trim() ||
    profile?.last_name?.trim() ||
    sessionParts?.lastName ||
    "";
  const email = current.email.trim() || profile?.email?.trim() || session?.email?.trim() || "";
  const phone = current.phone.trim() || profile?.phone?.trim() || "";

  return { firstName, lastName, email, phone };
}

export function applyCustomerAddressToCheckoutForm(
  form: CheckoutFormData,
  address: CustomerAddress,
): CheckoutFormData {
  const { firstName, lastName } = splitFullName(address.recipient_name);

  return {
    ...form,
    customer: {
      firstName: form.customer.firstName.trim() || firstName,
      lastName: form.customer.lastName.trim() || lastName,
      email: form.customer.email.trim() || "",
      phone: form.customer.phone.trim() || address.phone.trim(),
    },
    shippingAddress: mapCustomerAddressToShipping(address),
  };
}

export function buildDeliveryAddressPayloadFromCheckout(
  form: CheckoutFormData,
  selectedAddress: CustomerAddress | null,
): {
  recipient_name: string;
  phone: string;
  country: string;
  region: string;
  city: string;
  district: string;
  street: string;
  landmark: string | null;
  postal_code: string | null;
} {
  const recipientFromForm = `${form.customer.firstName} ${form.customer.lastName}`.trim();
  const recipient =
    selectedAddress?.recipient_name?.trim() || recipientFromForm || "Customer";

  const phone = selectedAddress?.phone?.trim() || form.customer.phone.trim();
  const shipping = form.shippingAddress;

  return {
    recipient_name: recipient,
    phone,
    country: shipping.country.trim() || selectedAddress?.country?.trim() || "Tanzania",
    region: shipping.region.trim() || selectedAddress?.region?.trim() || "",
    city: shipping.city.trim() || selectedAddress?.city?.trim() || "",
    district:
      shipping.addressLine2.trim() ||
      selectedAddress?.district?.trim() ||
      shipping.city.trim() ||
      "",
    street:
      shipping.addressLine1.trim() ||
      selectedAddress?.street?.trim() ||
      selectedAddress?.address_line_1?.trim() ||
      "",
    landmark: shipping.addressLine2.trim() || null,
    postal_code: shipping.postalCode.trim() || selectedAddress?.postal_code?.trim() || null,
  };
}

export function shouldSyncDefaultAddressForCheckout(
  selectedAddress: CustomerAddress | null,
): selectedAddress is CustomerAddress {
  return Boolean(selectedAddress && !selectedAddress.is_default);
}
