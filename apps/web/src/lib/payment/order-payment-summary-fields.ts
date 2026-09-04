import { formatPrice } from "@/lib/catalog/utils";
import { formatTrackingTimestamp } from "@/lib/order/tracking-format";
import { PAYMENT_METHOD_LABELS, PAYMENT_PROVIDER_LABELS } from "@/lib/payment/constants";
import type { PaymentMethodCode, PaymentStatus } from "@/lib/types/payment";

export type OrderPaymentSummaryInput = {
  paymentStatus?: PaymentStatus;
  paymentMethod?: PaymentMethodCode;
  paymentReference?: string | null;
  paymentProvider?: string | null;
  paymentAmount?: number | null;
  paymentCurrency?: string | null;
  paymentPaidAt?: string | null;
};

export type OrderPaymentSummaryField = {
  label: string;
  value: string;
};

export function formatPaymentProviderLabel(provider: string): string {
  const normalized = provider.trim().toLowerCase();
  return PAYMENT_PROVIDER_LABELS[normalized] ?? PAYMENT_METHOD_LABELS[normalized] ?? provider.trim();
}

export function formatPaymentAmount(amount: number, currency?: string | null): string {
  const code = currency?.trim().toUpperCase() || "TZS";
  const formatted = amount.toLocaleString("en-TZ", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });

  if (code === "TZS") {
    return formatPrice(amount);
  }

  return `${code} ${formatted}`;
}

export function buildOrderPaymentSummaryFields(
  input: OrderPaymentSummaryInput,
): OrderPaymentSummaryField[] {
  const fields: OrderPaymentSummaryField[] = [];

  if (input.paymentMethod) {
    fields.push({
      label: "Method",
      value: PAYMENT_METHOD_LABELS[input.paymentMethod] ?? input.paymentMethod,
    });
  }

  if (input.paymentProvider?.trim()) {
    fields.push({
      label: "Provider",
      value: formatPaymentProviderLabel(input.paymentProvider),
    });
  }

  if (input.paymentReference?.trim()) {
    fields.push({
      label: "Reference",
      value: input.paymentReference.trim(),
    });
  }

  if (input.paymentAmount != null) {
    fields.push({
      label: "Amount",
      value: formatPaymentAmount(input.paymentAmount, input.paymentCurrency),
    });
  }

  if (input.paymentPaidAt?.trim()) {
    fields.push({
      label: "Paid",
      value: formatTrackingTimestamp(input.paymentPaidAt),
    });
  }

  return fields;
}
