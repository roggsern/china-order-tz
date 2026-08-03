import type { ReceivingChoiceSnapshot } from "@/lib/api/customer-receiving-choice";
import {
  CUSTOMER_ORDER_PROGRESS_KEYS,
  parseCustomerOrderProgress,
  type CustomerOrderProgress,
} from "@/lib/order/customer-progress";
import type { OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS_LABELS } from "@/lib/payment/constants";

export type CustomerOrderDisplayStatusInput = {
  status: OrderStatus;
  progress?: CustomerOrderProgress | null | unknown;
  receivingChoice?: ReceivingChoiceSnapshot | null;
};

function isTerminalOrderDisplayStatus(
  status: OrderStatus,
  progress: CustomerOrderProgress | null,
): boolean {
  return (
    progress?.current_key === CUSTOMER_ORDER_PROGRESS_KEYS.DELIVERED ||
    status === "completed" ||
    status === "delivered"
  );
}

function resolveTerminalOrderDisplayLabel(
  status: OrderStatus,
  progress: CustomerOrderProgress | null,
): string {
  if (progress?.current_key === CUSTOMER_ORDER_PROGRESS_KEYS.DELIVERED) {
    return progress.current_label;
  }

  return ORDER_STATUS_LABELS[status] ?? status;
}

export function resolveCustomerOrderDisplayStatus(
  input: CustomerOrderDisplayStatusInput,
): string {
  const progress = parseCustomerOrderProgress(input.progress ?? null);
  const receivingChoice = input.receivingChoice;

  if (isTerminalOrderDisplayStatus(input.status, progress)) {
    return resolveTerminalOrderDisplayLabel(input.status, progress);
  }

  if (receivingChoice?.selected_method === "self_pickup") {
    return "Waiting for pickup";
  }

  if (receivingChoice?.selected_method === "negotiated_delivery") {
    return "Delivery arrangement pending";
  }

  if (progress) {
    if (progress.current_key === CUSTOMER_ORDER_PROGRESS_KEYS.ARRIVED_TANZANIA) {
      return "Arrived in Tanzania";
    }

    if (
      progress.current_key === CUSTOMER_ORDER_PROGRESS_KEYS.CHOOSE_RECEIVING_METHOD &&
      (receivingChoice?.can_select || receivingChoice?.eligible)
    ) {
      return "Arrived in Tanzania";
    }

    if (progress.current_key === CUSTOMER_ORDER_PROGRESS_KEYS.SHIPPED) {
      return ORDER_STATUS_LABELS.shipped ?? "Shipping";
    }

    return progress.current_label;
  }

  return ORDER_STATUS_LABELS[input.status] ?? input.status;
}
