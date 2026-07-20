"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerReturnsApiError,
  fetchCustomerReturns,
  type CustomerReturnRequest,
} from "@/lib/api/customer-returns";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import {
  isAuthRequiredMessage,
  toFriendlyAuthMessage,
} from "@/lib/auth/friendly-auth-messages";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorState } from "@/components/ui/ErrorState";
import { Skeleton } from "@/components/ui/Skeleton";

const STATUS_STYLES: Record<string, string> = {
  requested: "bg-amber-50 text-amber-800 ring-amber-600/20",
  approved: "bg-blue-50 text-blue-800 ring-blue-600/20",
  rejected: "bg-red-50 text-red-800 ring-red-600/20",
  inspection: "bg-sky-50 text-sky-800 ring-sky-600/20",
  completed: "bg-green-50 text-green-800 ring-green-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/40",
};

function formatWhen(value?: string | null): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat("en-TZ", {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export function CustomerReturnsListContent() {
  const [rows, setRows] = useState<CustomerReturnRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [needsAuth, setNeedsAuth] = useState(false);

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
      const data = await fetchCustomerReturns();
      setRows(data);
    } catch (err) {
      setRows([]);
      if (err instanceof CustomerReturnsApiError) {
        if (isAuthRequiredMessage(err.message) || err.statusCode === 401) {
          setNeedsAuth(true);
        } else {
          setError(toFriendlyAuthMessage(err.message, err.message));
        }
      } else {
        setError("Unable to load returns.");
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6" aria-busy="true">
        <Skeleton className="h-8 w-40" rounded="lg" />
        <Skeleton className="mt-6 h-48 w-full" rounded="3xl" />
      </div>
    );
  }

  if (needsAuth) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <AuthInvitationCard context="orders" returnUrl="/returns" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <ErrorState message={error} onRetry={() => void load()} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-10">
      <header className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-sm sm:p-7">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
          Account
        </p>
        <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-900">
          My returns
        </h1>
        <p className="mt-2 text-sm text-zinc-500">
          Track return requests and refund progress for your orders.
        </p>
        <div className="mt-4">
          <Link
            href="/orders"
            className="text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
          >
            Browse orders to request a return
          </Link>
        </div>
      </header>

      <div className="mt-6 overflow-hidden rounded-3xl border border-zinc-200/70 bg-white shadow-sm">
        {rows.length === 0 ? (
          <div className="p-8">
            <EmptyState
              icon="↩️"
              title="No returns yet"
              description="When you request a return from a delivered order, it will appear here."
              primaryAction={{ label: "View My Orders", href: "/orders" }}
            />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-zinc-100 bg-zinc-50/80 text-[11px] uppercase tracking-wide text-zinc-500">
                <tr>
                  <th className="px-4 py-3 font-semibold">Order</th>
                  <th className="px-4 py-3 font-semibold">Reason</th>
                  <th className="px-4 py-3 font-semibold">Status</th>
                  <th className="px-4 py-3 font-semibold">Items</th>
                  <th className="px-4 py-3 font-semibold">Requested</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-zinc-50">
                    <td className="px-4 py-3">
                      {row.order?.order_number ? (
                        <Link
                          href={`/orders/${encodeURIComponent(row.order.order_number)}`}
                          className="font-mono text-xs font-semibold text-[#8b6914] hover:underline"
                        >
                          {row.order.order_number}
                        </Link>
                      ) : (
                        <span className="font-mono text-xs text-zinc-500">
                          {row.order_id.slice(0, 8)}…
                        </span>
                      )}
                    </td>
                    <td className="max-w-[220px] truncate px-4 py-3 text-zinc-700">
                      {row.reason}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset ${
                          STATUS_STYLES[row.status] ??
                          "bg-zinc-50 text-zinc-700 ring-zinc-300/40"
                        }`}
                      >
                        {row.status_label ?? row.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-zinc-600">
                      {row.items?.length ?? "—"}
                    </td>
                    <td className="px-4 py-3 text-xs text-zinc-500">
                      {formatWhen(row.created_at)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
