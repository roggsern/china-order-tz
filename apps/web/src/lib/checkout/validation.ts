import type { CheckoutFormData, CheckoutFormErrors } from "@/lib/types/checkout";
import { formatTanzaniaPhone, isValidTanzaniaPhone, TZ_PHONE_VALIDATION_MESSAGE } from "@/lib/checkout/phone";
import { isValidTanzaniaRegion } from "@/lib/checkout/tanzania-regions";

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const NAME_PATTERN = /^[\p{L}\s'.-]+$/u;

export function validateFirstName(value: string): string | undefined {
  const trimmed = value.trim();
  if (!trimmed) {
    return "First name is required";
  }
  if (trimmed.length < 2) {
    return "Enter a valid first name";
  }
  if (!NAME_PATTERN.test(trimmed)) {
    return "First name can only contain letters, spaces, and hyphens";
  }
  return undefined;
}

export function validateLastName(value: string): string | undefined {
  const trimmed = value.trim();
  if (!trimmed) {
    return "Last name is required";
  }
  if (trimmed.length < 2) {
    return "Enter a valid last name";
  }
  if (!NAME_PATTERN.test(trimmed)) {
    return "Last name can only contain letters, spaces, and hyphens";
  }
  return undefined;
}

export function validateEmail(value: string): string | undefined {
  const trimmed = value.trim();
  if (!trimmed) {
    return "Email address is required";
  }
  if (!EMAIL_PATTERN.test(trimmed)) {
    return "Enter a valid email address";
  }
  return undefined;
}

export function validatePhone(value: string): string | undefined {
  const trimmed = value.trim();
  if (!trimmed) {
    return "Phone number is required";
  }
  if (!isValidTanzaniaPhone(trimmed)) {
    return TZ_PHONE_VALIDATION_MESSAGE;
  }
  return undefined;
}

export function validateAddressLine1(value: string): string | undefined {
  const trimmed = value.trim();
  if (!trimmed) {
    return "Street address is required";
  }
  if (trimmed.length < 5) {
    return "Please enter a complete street address";
  }
  return undefined;
}

export function validateCity(value: string): string | undefined {
  const trimmed = value.trim();
  if (!trimmed) {
    return "City is required";
  }
  if (trimmed.length < 2) {
    return "Enter a valid city name";
  }
  if (!NAME_PATTERN.test(trimmed)) {
    return "City name contains invalid characters";
  }
  return undefined;
}

export function validateRegion(value: string): string | undefined {
  const trimmed = value.trim();
  if (!trimmed) {
    return "Region is required";
  }
  if (!isValidTanzaniaRegion(trimmed)) {
    return "Select a valid Tanzanian region";
  }
  return undefined;
}

export function validateCheckoutForm(form: CheckoutFormData): CheckoutFormErrors {
  const errors: CheckoutFormErrors = {};

  const firstName = validateFirstName(form.customer.firstName);
  if (firstName) {
    errors.customer = { ...errors.customer, firstName };
  }

  const lastName = validateLastName(form.customer.lastName);
  if (lastName) {
    errors.customer = { ...errors.customer, lastName };
  }

  const email = validateEmail(form.customer.email);
  if (email) {
    errors.customer = { ...errors.customer, email };
  }

  const phone = validatePhone(form.customer.phone);
  if (phone) {
    errors.customer = { ...errors.customer, phone };
  }

  const addressLine1 = validateAddressLine1(form.shippingAddress.addressLine1);
  if (addressLine1) {
    errors.shippingAddress = { ...errors.shippingAddress, addressLine1 };
  }

  const city = validateCity(form.shippingAddress.city);
  if (city) {
    errors.shippingAddress = { ...errors.shippingAddress, city };
  }

  const region = validateRegion(form.shippingAddress.region);
  if (region) {
    errors.shippingAddress = { ...errors.shippingAddress, region };
  }

  return errors;
}

export function hasCheckoutErrors(errors: CheckoutFormErrors): boolean {
  const customerErrors = errors.customer ? Object.keys(errors.customer).length > 0 : false;
  const addressErrors = errors.shippingAddress
    ? Object.keys(errors.shippingAddress).length > 0
    : false;
  return customerErrors || addressErrors;
}

/** Normalize form values before validation/submit. */
export function normalizeCheckoutForm(form: CheckoutFormData): CheckoutFormData {
  return {
    ...form,
    customer: {
      ...form.customer,
      firstName: form.customer.firstName.trim(),
      lastName: form.customer.lastName.trim(),
      email: form.customer.email.trim(),
      phone: formatTanzaniaPhone(form.customer.phone),
    },
    shippingAddress: {
      ...form.shippingAddress,
      addressLine1: form.shippingAddress.addressLine1.trim(),
      addressLine2: form.shippingAddress.addressLine2.trim(),
      city: form.shippingAddress.city.trim(),
      region: form.shippingAddress.region.trim(),
      postalCode: form.shippingAddress.postalCode.trim(),
    },
    orderNotes: form.orderNotes.trim(),
  };
}
