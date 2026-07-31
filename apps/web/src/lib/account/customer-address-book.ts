import type { CustomerAddressInput } from "@/lib/api/customer-addresses";

export type AddressBookFormValues = {
  label: string;
  recipient_name: string;
  phone: string;
  street: string;
  district: string;
  city: string;
  region: string;
  country: string;
  postal_code: string;
  is_default: boolean;
};

export const EMPTY_ADDRESS_BOOK_FORM: AddressBookFormValues = {
  label: "",
  recipient_name: "",
  phone: "",
  street: "",
  district: "",
  city: "",
  region: "",
  country: "Tanzania",
  postal_code: "",
  is_default: false,
};

export function validateAddressBookForm(
  values: AddressBookFormValues,
): Partial<Record<keyof AddressBookFormValues, string>> {
  const errors: Partial<Record<keyof AddressBookFormValues, string>> = {};

  if (!values.recipient_name.trim()) {
    errors.recipient_name = "Recipient name is required.";
  }
  if (!values.phone.trim()) {
    errors.phone = "Phone number is required.";
  } else if (!/^\+?[0-9\s-]{9,20}$/.test(values.phone.trim())) {
    errors.phone = "Enter a valid phone number.";
  }
  if (!values.street.trim()) {
    errors.street = "Street address is required.";
  }
  if (!values.district.trim()) {
    errors.district = "District is required.";
  }
  if (!values.city.trim()) {
    errors.city = "City is required.";
  }
  if (!values.region.trim()) {
    errors.region = "Region is required.";
  }

  return errors;
}

export function toCustomerAddressInput(values: AddressBookFormValues): CustomerAddressInput {
  return {
    label: values.label.trim() || null,
    recipient_name: values.recipient_name.trim(),
    phone: values.phone.trim(),
    street: values.street.trim(),
    district: values.district.trim(),
    city: values.city.trim(),
    region: values.region.trim(),
    country: values.country.trim() || "Tanzania",
    postal_code: values.postal_code.trim() || null,
    is_default: values.is_default,
  };
}

export function formatAddressLines(address: {
  street?: string | null;
  district?: string | null;
  city?: string | null;
  region?: string | null;
  postal_code?: string | null;
  country?: string | null;
}): string {
  return [
    address.street,
    address.district,
    address.city,
    address.region,
    address.postal_code,
    address.country,
  ]
    .map((part) => part?.trim())
    .filter(Boolean)
    .join(", ");
}
