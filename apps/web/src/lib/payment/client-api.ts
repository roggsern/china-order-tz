import type { Order } from "@/lib/types/order";
import type {
  InitiateStkPushResult,
  PaymentConfigResponse,
  SimulateStkPushResult,
  VerifyPaymentResult,
} from "@/lib/payments/types";

export async function initiatePaymentRequest(order: Order): Promise<InitiateStkPushResult> {
  const response = await fetch("/api/payments/initiate", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      orderId: order.id,
      orderNumber: order.orderNumber,
      amount: order.totals.grandTotal,
      phone: order.customer.phone,
      accountReference: order.orderNumber,
      description: "CHINA ORDER TZ",
      paymentMethod: order.paymentMethod,
    }),
  });

  const data = (await response.json()) as InitiateStkPushResult & { error?: string };

  if (response.status >= 500) {
    throw new Error(data.error ?? `Payment initiation failed (${response.status})`);
  }

  return data;
}

export async function verifyPaymentRequest(transactionId: string): Promise<VerifyPaymentResult> {
  const response = await fetch(
    `/api/payments/verify?transactionId=${encodeURIComponent(transactionId)}`,
    { method: "GET", cache: "no-store" },
  );

  const data = (await response.json()) as VerifyPaymentResult & { error?: string };

  if (!response.ok) {
    throw new Error(data.error ?? `Payment verification failed (${response.status})`);
  }

  return data;
}

export async function fetchPaymentConfig(): Promise<PaymentConfigResponse> {
  const response = await fetch("/api/payments/config", { cache: "no-store" });
  const data = (await response.json()) as PaymentConfigResponse & { error?: string };

  if (!response.ok) {
    throw new Error(data.error ?? "Unable to load payment configuration.");
  }

  return data;
}

export async function simulatePaymentRequest(order: Order): Promise<SimulateStkPushResult> {
  const response = await fetch("/api/payments/simulate", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      orderId: order.id,
      orderNumber: order.orderNumber,
      amount: order.totals.grandTotal,
      phone: order.customer.phone,
      accountReference: order.orderNumber,
      description: "CHINA ORDER TZ TEST",
    }),
  });

  const data = (await response.json()) as SimulateStkPushResult & { error?: string };

  if (!response.ok) {
    throw new Error(data.error ?? `Simulate payment failed (${response.status})`);
  }

  return data;
}
