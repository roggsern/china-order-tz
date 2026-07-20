"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerOrderApiError,
  fetchCustomerOrder,
} from "@/lib/api/customer-orders";
import {
  createCustomerReturn,
  CustomerReturnsApiError,
} from "@/lib/api/customer-returns";
import type { Order, OrderLineItem } from "@/lib/types/order";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import {
  isAuthRequiredMessage,
  toFriendlyAuthMessage,
} from "@/lib/auth/friendly-auth-messages";
import { ErrorState } from "@/components/ui/ErrorState";
import { Skeleton } from "@/components/ui/Skeleton";

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

const REASON_OPTIONS = [
  "Damaged on arrival",
  "Wrong item received",
  "Not as described",
  "Changed mind",
  "Other",
];

type PageProps = {
  orderNumber: string;
};

type ItemSelection = {
  selected: boolean;
  quantity: number;
};

function isReturnableStatus(status: string): boolean {
  return status === "delivered" || status === "completed";
}

export function CustomerReturnRequestContent({ orderNumber }: PageProps) {
  const router = useRouter();
  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [needsAuth, setNeedsAuth] = useState(false);
  const [reason, setReason] = useState(REASON_OPTIONS[0]);
  const [description, setDescription] = useState("");
  const [customerNotes, setCustomerNotes] = useState("");
  const [selections, setSelections] = useState<Record<string, ItemSelection>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    setNeedsAuth(false);

    if (!getCustomerApiToken()) {
      setNeedsAuth(true);
      setLoading(false);
      return;
    }

    try {
      const loaded = await fetchCustomerOrder(orderNumber);
      setOrder(loaded);
      const next: Record<string, ItemSelection> = {};
      for (const item of loaded.items) {
        next[item.id] = { selected: false, quantity: item.quantity };
      }
      setSelections(next);
    } catch (err) {
      if (err instanceof CustomerOrderApiError) {
        if (isAuthRequiredMessage(err.message) || err.statusCode === 401) {
          setNeedsAuth(true);
        } else {
          setError(toFriendlyAuthMessage(err.message, err.message));
        }
      } else {
        setError("Unable to load this order.");
      }
      setOrder(null);
    } finally {
      setLoading(false);
    }
  }, [orderNumber]);

  useEffect(() => {
    void load();
  }, [load]);

  const returnableItems = useMemo(() => {
    if (!order) return [] as OrderLineItem[];
    return order.items.filter((item) => UUID_RE.test(item.id));
  }, [order]);

  const toggleItem = (itemId: string) => {
    setSelections((prev) => ({
      ...prev,
      [itemId]: {
        selected: !prev[itemId]?.selected,
        quantity: prev[itemId]?.quantity ?? 1,
      },
    }));
  };

  const setQty = (itemId: string, quantity: number, max: number) => {
    const clamped = Math.max(1, Math.min(max, quantity));
    setSelections((prev) => ({
      ...prev,
      [itemId]: {
        selected: prev[itemId]?.selected ?? true,
        quantity: clamped,
      },
    }));
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (!order || submitting) return;

    const items = returnableItems
      .filter((item) => selections[item.id]?.selected)
      .map((item) => ({
        order_item_id: item.id,
        quantity: selections[item.id]?.quantity ?? 1,
      }));

    if (items.length === 0) {
      setError("Select at least one item to return.");
      return;
    }

    if (!reason.trim()) {
      setError("Choose a return reason.");
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      await createCustomerReturn(order.orderNumber, {
        reason: reason.trim(),
        description: description.trim() || null,
        customer_notes: customerNotes.trim() || null,
        items,
      });
      router.push("/returns");
    } catch (err) {
      setError(
        err instanceof CustomerReturnsApiError
          ? err.message
          : "Unable to submit return request.",
      );
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6" aria-busy="true">
        <Skeleton className="h-8 w-48" rounded="lg" />
        <Skeleton className="mt-6 h-64 w-full" rounded="3xl" />
      </div>
    );
  }

  if (needsAuth) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <AuthInvitationCard
          context="orders"
          returnUrl={`/orders/${encodeURIComponent(orderNumber)}/return`}
        />
      </div>
    );
  }

  if (error && !order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <ErrorState message={error} onRetry={() => void load()} />
        <div className="mt-4 text-center">
          <Link href="/orders" className="text-sm font-semibold text-[#8b6914]">
            View My Orders
          </Link>
        </div>
      </div>
    );
  }

  if (!order) {
    return null;
  }

  if (!isReturnableStatus(order.status)) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
          <h1 className="text-xl font-bold text-zinc-900">Return not available</h1>
          <p className="mt-2 text-sm text-zinc-600">
            Returns can be requested after an order is delivered or completed.
          </p>
          <Link
            href={`/orders/${encodeURIComponent(order.orderNumber)}`}
            className="mt-4 inline-flex text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
          >
            Back to order
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10">
      <nav aria-label="Breadcrumb" className="mb-6">
        <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
          <li>
            <Link href="/orders" className="font-medium hover:text-[#8b6914]">
              My Orders
            </Link>
          </li>
          <li aria-hidden>/</li>
          <li>
            <Link
              href={`/orders/${encodeURIComponent(order.orderNumber)}`}
              className="font-mono font-medium hover:text-[#8b6914]"
            >
              {order.orderNumber}
            </Link>
          </li>
          <li aria-hidden>/</li>
          <li className="font-semibold text-zinc-900">Request return</li>
        </ol>
      </nav>

      <header className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-sm sm:p-7">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
          Returns
        </p>
        <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-900">
          Request a return
        </h1>
        <p className="mt-2 text-sm text-zinc-500">
          Select items from order{" "}
          <span className="font-mono font-semibold text-zinc-800">{order.orderNumber}</span>{" "}
          and tell us why you are returning them.
        </p>
      </header>

      {error ? (
        <p
          className="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
          role="alert"
        >
          {error}
        </p>
      ) : null}

      <form onSubmit={(e) => void submit(e)} className="mt-6 space-y-6">
        <section className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-sm sm:p-7">
          <h2 className="text-lg font-bold text-zinc-900">Items</h2>
          {returnableItems.length === 0 ? (
            <p className="mt-3 text-sm text-zinc-500">
              Order line items are unavailable for return selection. Please contact support.
            </p>
          ) : (
            <ul className="mt-4 space-y-3">
              {returnableItems.map((item) => {
                const sel = selections[item.id];
                return (
                  <li
                    key={item.id}
                    className="flex flex-col gap-3 rounded-xl border border-zinc-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                  >
                    <label className="flex cursor-pointer items-start gap-3">
                      <input
                        type="checkbox"
                        checked={Boolean(sel?.selected)}
                        onChange={() => toggleItem(item.id)}
                        className="mt-1 rounded border-zinc-300"
                      />
                      <span>
                        <span className="block text-sm font-semibold text-zinc-900">
                          {item.name}
                        </span>
                        <span className="mt-0.5 block text-xs text-zinc-500">
                          Ordered qty {item.quantity}
                          {item.configurationLabel ? ` · ${item.configurationLabel}` : ""}
                        </span>
                      </span>
                    </label>
                    <div className="flex items-center gap-2 sm:pl-8">
                      <label htmlFor={`qty-${item.id}`} className="text-xs text-zinc-500">
                        Return qty
                      </label>
                      <input
                        id={`qty-${item.id}`}
                        type="number"
                        min={1}
                        max={item.quantity}
                        disabled={!sel?.selected}
                        value={sel?.quantity ?? 1}
                        onChange={(e) =>
                          setQty(item.id, Number(e.target.value) || 1, item.quantity)
                        }
                        className="w-20 rounded-lg border border-zinc-200 px-2 py-1.5 text-sm disabled:bg-zinc-50"
                      />
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </section>

        <section className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-sm sm:p-7">
          <h2 className="text-lg font-bold text-zinc-900">Reason</h2>
          <label htmlFor="return-reason" className="mt-4 block text-xs font-semibold text-zinc-500">
            Primary reason
          </label>
          <select
            id="return-reason"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            className="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm"
          >
            {REASON_OPTIONS.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>

          <label
            htmlFor="return-description"
            className="mt-4 block text-xs font-semibold text-zinc-500"
          >
            Description (optional)
          </label>
          <textarea
            id="return-description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
            className="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2.5 text-sm"
            placeholder="Add details about the issue"
          />

          <label
            htmlFor="return-notes"
            className="mt-4 block text-xs font-semibold text-zinc-500"
          >
            Notes for us (optional)
          </label>
          <textarea
            id="return-notes"
            value={customerNotes}
            onChange={(e) => setCustomerNotes(e.target.value)}
            rows={2}
            className="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2.5 text-sm"
            placeholder="Pickup preference, packaging notes, etc."
          />
        </section>

        <div className="flex flex-wrap gap-3">
          <button
            type="submit"
            disabled={submitting || returnableItems.length === 0}
            className="inline-flex min-h-11 items-center justify-center rounded-xl bg-zinc-900 px-5 text-sm font-bold text-white transition hover:bg-zinc-800 disabled:opacity-50"
          >
            {submitting ? "Submitting…" : "Submit return request"}
          </button>
          <Link
            href={`/orders/${encodeURIComponent(order.orderNumber)}`}
            className="inline-flex min-h-11 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 text-sm font-semibold text-zinc-700 hover:border-zinc-300"
          >
            Cancel
          </Link>
        </div>
      </form>
    </div>
  );
}
