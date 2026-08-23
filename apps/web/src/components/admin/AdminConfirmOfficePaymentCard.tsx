"use client";

import { useState } from "react";
import type { Order } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import { PAYMENT_STATUS_LABELS } from "@/lib/payment/constants";
import {
  AdminOrdersApiError,
  canConfirmOfficePayment,
  confirmOfficePayment,
  isEligibleOfficePaymentOrder,
} from "@/lib/api/admin-orders";

type AdminConfirmOfficePaymentCardProps = {
  order: Order;
  permissions: string[] | undefined;
  permissionsLoading?: boolean;
  onConfirmed: () => void;
};

export function AdminConfirmOfficePaymentCard({
  order,
  permissions,
  permissionsLoading = false,
  onConfirmed,
}: AdminConfirmOfficePaymentCardProps) {
  const [reference, setReference] = useState("");
  const [note, setNote] = useState("");
  const [confirming, setConfirming] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const authorized = canConfirmOfficePayment(permissions);
  const eligible = isEligibleOfficePaymentOrder(order);

  if (permissionsLoading || !authorized || !eligible) {
    return null;
  }

  const amountLabel = formatPrice(order.grandTotal);
  const methodLabel =
    PAYMENT_METHOD_LABELS[order.paymentMethod ?? ""] ?? "Pay at Office";
  const paymentStatusLabel =
    PAYMENT_STATUS_LABELS[order.paymentStatus] ?? order.paymentStatus;

  const submit = async () => {
    if (pending) {
      return;
    }

    setPending(true);
    setError(null);
    try {
      await confirmOfficePayment(order.id, {
        reference: reference.trim() || undefined,
        note: note.trim() || undefined,
      });
      setConfirming(false);
      onConfirmed();
    } catch (err) {
      setError(
        err instanceof AdminOrdersApiError
          ? err.message
          : "Unable to confirm Pay at Office payment.",
      );
    } finally {
      setPending(false);
    }
  };

  return (
    <section className="admin-card p-4 sm:p-5">
      <h2 className="text-sm font-semibold text-zinc-900">Pay at Office</h2>
      <dl className="mt-3 grid gap-2 text-sm">
        <div className="flex justify-between gap-3">
          <dt className="text-zinc-500">Order reference</dt>
          <dd className="font-mono font-semibold text-zinc-900">{order.orderNumber}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-zinc-500">Payment method</dt>
          <dd className="font-semibold text-zinc-900">{methodLabel}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-zinc-500">Expected amount</dt>
          <dd className="font-semibold text-zinc-900">{amountLabel}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-zinc-500">Currency</dt>
          <dd className="font-semibold text-zinc-900">TZS</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-zinc-500">Current payment status</dt>
          <dd className="font-semibold text-zinc-900">{paymentStatusLabel}</dd>
        </div>
      </dl>

      <label className="mt-4 block text-xs font-medium text-zinc-600">
        Reference (optional)
        <input
          value={reference}
          onChange={(event) => setReference(event.target.value)}
          disabled={pending}
          className="admin-input mt-1"
          maxLength={255}
        />
      </label>
      <label className="mt-3 block text-xs font-medium text-zinc-600">
        Internal note (optional)
        <textarea
          value={note}
          onChange={(event) => setNote(event.target.value)}
          disabled={pending}
          className="admin-input mt-1 min-h-[72px]"
          maxLength={1000}
        />
      </label>

      {error ? (
        <p role="alert" className="mt-3 text-sm text-red-700">
          {error}
        </p>
      ) : null}

      {confirming ? (
        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3">
          <p className="text-sm text-amber-950">
            Confirm that {amountLabel} has been physically received for order {order.orderNumber}.
            This action will mark the payment and order as paid and may start fulfillment.
          </p>
          <div className="mt-3 flex gap-2">
            <button
              type="button"
              disabled={pending}
              onClick={() => void submit()}
              className="admin-btn-primary disabled:cursor-not-allowed disabled:opacity-60"
            >
              {pending ? "Confirming…" : "Confirm payment received"}
            </button>
            <button
              type="button"
              disabled={pending}
              onClick={() => setConfirming(false)}
              className="admin-btn-secondary disabled:opacity-60"
            >
              Cancel
            </button>
          </div>
        </div>
      ) : (
        <button
          type="button"
          disabled={pending}
          onClick={() => {
            setError(null);
            setConfirming(true);
          }}
          className="admin-btn-primary mt-4 disabled:cursor-not-allowed disabled:opacity-60"
        >
          Confirm payment received
        </button>
      )}
    </section>
  );
}
