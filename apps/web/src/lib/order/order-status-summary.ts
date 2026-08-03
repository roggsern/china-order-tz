import {
  CUSTOMER_ORDER_PROGRESS_KEYS,
  parseCustomerOrderProgress,
  type CustomerOrderProgress,
} from "@/lib/order/customer-progress";
import { resolveCustomerOrderDisplayStatus } from "@/lib/order/customer-order-display-status";
import type { Order } from "@/lib/types/order";
import type { PaymentStatus } from "@/lib/types/payment";
import { ORDER_STATUS_LABELS } from "@/lib/payment/constants";

export type CompactOrderStatusLine = {
  label: string;
  completed: boolean;
};

export type CompactOrderStatusSummary = {
  completedLines: CompactOrderStatusLine[];
  currentStatus: string;
};

function isPaymentComplete(paymentStatus: PaymentStatus): boolean {
  return paymentStatus === "paid" || paymentStatus === "refunded";
}

function paymentSummaryLine(paymentStatus: PaymentStatus): CompactOrderStatusLine | null {
  if (isPaymentComplete(paymentStatus)) {
    return { label: "Payment completed", completed: true };
  }

  if (paymentStatus === "pending" || paymentStatus === "pending_payment") {
    return { label: "Payment pending", completed: false };
  }

  if (paymentStatus === "failed" || paymentStatus === "cancelled") {
    return { label: "Payment incomplete", completed: false };
  }

  return null;
}

function milestoneLinesFromProgress(
  progress: CustomerOrderProgress,
): CompactOrderStatusLine[] {
  const confirmedStep = progress.steps.find(
    (step) =>
      step.key === CUSTOMER_ORDER_PROGRESS_KEYS.ORDER_CONFIRMED &&
      step.completed &&
      step.key !== progress.current_key,
  );

  if (!confirmedStep) {
    return [];
  }

  return [{ label: confirmedStep.label, completed: true }];
}

function fallbackCurrentStatus(order: Pick<Order, "status">): string {
  return ORDER_STATUS_LABELS[order.status] ?? order.status;
}

export function buildCompactOrderStatusSummary(
  order: Pick<Order, "status" | "paymentStatus" | "progress" | "receivingChoice">,
): CompactOrderStatusSummary {
  const progress = parseCustomerOrderProgress(order.progress);
  const completedLines: CompactOrderStatusLine[] = [];

  const paymentLine = paymentSummaryLine(order.paymentStatus);
  if (paymentLine?.completed) {
    completedLines.push(paymentLine);
  }

  if (progress) {
    completedLines.push(...milestoneLinesFromProgress(progress));

    if (paymentLine && !paymentLine.completed) {
      completedLines.push(paymentLine);
    }

    return {
      completedLines,
      currentStatus: resolveCustomerOrderDisplayStatus({
        status: order.status,
        progress,
        receivingChoice: order.receivingChoice,
      }),
    };
  }

  if (paymentLine && !paymentLine.completed) {
    completedLines.push(paymentLine);
  }

  return {
    completedLines,
    currentStatus: fallbackCurrentStatus(order),
  };
}
