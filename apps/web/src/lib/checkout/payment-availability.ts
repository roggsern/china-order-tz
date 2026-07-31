import type { CheckoutPaymentAvailability } from "@/lib/api/checkout-payment-methods";
import { SIMPLIFIED_PAYMENT_OPTIONS } from "@/lib/payment/constants";
import type { PaymentMethodCode } from "@/lib/types/payment";

export type CheckoutPaymentOption = {
  code: PaymentMethodCode;
  backendCode: string;
  label: string;
  description: string;
  icon: string;
};

/** Map Laravel PaymentMethod values to storefront payment codes. */
export function backendMethodToStorefrontCode(backendCode: string): PaymentMethodCode | null {
  switch (backendCode) {
    case "nmb":
      return "nmb";
    case "mpesa":
      return "mpesa";
    case "card":
      return "card";
    case "bank_transfer":
      return "bank_transfer";
    case "cash":
      return "cod";
    default:
      return null;
  }
}

/** Map storefront codes to Laravel PaymentMethod values. */
export function storefrontCodeToBackendMethod(code: string): string | null {
  switch (code) {
    case "nmb":
      return "nmb";
    case "mpesa":
      return "mpesa";
    case "card":
      return "card";
    case "bank_transfer":
      return "bank_transfer";
    case "cod":
      return "cash";
    default:
      return null;
  }
}

/**
 * Build checkout selector options from backend availability.
 * Hides disabled (and non-selectable) methods.
 */
export function buildCheckoutPaymentOptions(
  availability: CheckoutPaymentAvailability,
): CheckoutPaymentOption[] {
  const selectable = new Set(
    availability.methods.filter((row) => row.selectable).map((row) => row.code),
  );

  const options: CheckoutPaymentOption[] = [];

  for (const backendCode of availability.enabled_methods) {
    if (!selectable.has(backendCode)) {
      continue;
    }

    const storefrontCode = backendMethodToStorefrontCode(backendCode);
    if (!storefrontCode) {
      continue;
    }

    const catalog = SIMPLIFIED_PAYMENT_OPTIONS.find((option) => option.code === storefrontCode);
    options.push({
      code: storefrontCode,
      backendCode,
      label: catalog?.label ?? storefrontCode,
      description: catalog?.description ?? "Available at checkout",
      icon: catalog?.icon ?? "💳",
    });
  }

  return options;
}

export function resolveDefaultCheckoutPaymentCode(
  availability: CheckoutPaymentAvailability,
  options: CheckoutPaymentOption[],
): PaymentMethodCode | null {
  if (options.length === 0) {
    return null;
  }

  const preferred = backendMethodToStorefrontCode(availability.default_provider);
  if (preferred && options.some((option) => option.code === preferred)) {
    return preferred;
  }

  return options[0]?.code ?? null;
}
