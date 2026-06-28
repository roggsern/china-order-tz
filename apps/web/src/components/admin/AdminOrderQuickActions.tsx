"use client";

import { useState } from "react";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

interface AdminOrderQuickActionsProps {
  order: Order;
  onMarkPaid: () => void;
  onMarkProcessing: () => void;
  onMarkShipped: () => Promise<void>;
  onMarkDelivered: () => Promise<void>;
}

function canManageOrder(order: Order): boolean {
  return order.status !== ORDER_STATUS.CANCELLED && order.status !== ORDER_STATUS.DELIVERED;
}

export function AdminOrderQuickActions({
  order,
  onMarkPaid,
  onMarkProcessing,
  onMarkShipped,
  onMarkDelivered,
}: AdminOrderQuickActionsProps) {
  const [busyAction, setBusyAction] = useState<string | null>(null);
  const editable = canManageOrder(order);
  const isPaid = order.paymentStatus === PAYMENT_STATUS.PAID;
  const isProcessing =
    order.status === ORDER_STATUS.PROCESSING || order.status === ORDER_STATUS.PACKED;
  const isShipped =
    order.status === ORDER_STATUS.SHIPPED || order.status === ORDER_STATUS.IN_TRANSIT;

  const runAsync = async (actionId: string, action: () => Promise<void>) => {
    if (busyAction) {
      return;
    }
    setBusyAction(actionId);
    try {
      await action();
    } finally {
      setBusyAction(null);
    }
  };

  if (!editable) {
    return (
      <p className="text-sm text-zinc-500">
        {order.status === ORDER_STATUS.DELIVERED
          ? "This order has been delivered."
          : "This order was cancelled."}
      </p>
    );
  }

  return (
    <div className="grid gap-2 sm:grid-cols-1">
      {!isPaid && (
        <QuickActionButton
          label="Mark as Paid"
          tone="success"
          disabled={Boolean(busyAction)}
          onClick={onMarkPaid}
        />
      )}
      {!isProcessing && order.status !== ORDER_STATUS.DELIVERED && (
        <QuickActionButton
          label="Mark as Processing"
          disabled={Boolean(busyAction)}
          loading={busyAction === "processing"}
          onClick={onMarkProcessing}
        />
      )}
      {!isShipped && order.status !== ORDER_STATUS.DELIVERED && (
        <QuickActionButton
          label="Mark as Shipped"
          disabled={Boolean(busyAction)}
          loading={busyAction === "shipped"}
          onClick={() => void runAsync("shipped", onMarkShipped)}
        />
      )}
      {order.status !== ORDER_STATUS.DELIVERED && (
        <QuickActionButton
          label="Mark as Delivered"
          tone="gold"
          disabled={Boolean(busyAction)}
          loading={busyAction === "delivered"}
          onClick={() => void runAsync("delivered", onMarkDelivered)}
        />
      )}
    </div>
  );
}

function QuickActionButton({
  label,
  onClick,
  disabled,
  loading,
  tone = "default",
}: {
  label: string;
  onClick: () => void;
  disabled?: boolean;
  loading?: boolean;
  tone?: "default" | "success" | "gold";
}) {
  const toneClasses = {
    default: "border border-zinc-200 bg-white text-zinc-800 hover:bg-zinc-50",
    success: "bg-emerald-600 text-white hover:bg-emerald-700",
    gold: "bg-[#c9a227] text-zinc-900 hover:bg-[#e8c547]",
  };

  return (
    <button
      type="button"
      disabled={disabled || loading}
      onClick={onClick}
      className={`flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 ${toneClasses[tone]}`}
    >
      {loading && (
        <span className="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
      )}
      {label}
    </button>
  );
}
