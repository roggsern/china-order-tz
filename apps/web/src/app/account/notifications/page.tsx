"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerNotificationsApiError,
  fetchCustomerNotifications,
  markAllCustomerNotificationsRead,
  markCustomerNotificationRead,
  type CustomerNotification,
} from "@/lib/api/customer-notifications";

function formatWhen(value?: string | null): string {
  if (!value) return "";
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export default function AccountNotificationsPage() {
  const { isReady, isLoggedIn } = useCustomerSession();
  const [rows, setRows] = useState<CustomerNotification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    if (!getCustomerApiToken()) {
      setRows([]);
      setError("Sign in with your account to view notification history.");
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const { notifications } = await fetchCustomerNotifications({ perPage: 50 });
      setRows(notifications);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof CustomerNotificationsApiError
          ? err.message
          : "Unable to load notifications.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isReady) return;
    void reload();
  }, [isReady, isLoggedIn, reload]);

  const onMarkRead = async (id: string) => {
    setBusy(true);
    try {
      await markCustomerNotificationRead(id);
      await reload();
    } catch (err) {
      setError(
        err instanceof CustomerNotificationsApiError
          ? err.message
          : "Unable to mark as read.",
      );
    } finally {
      setBusy(false);
    }
  };

  const onMarkAll = async () => {
    setBusy(true);
    try {
      await markAllCustomerNotificationsRead();
      await reload();
    } catch (err) {
      setError(
        err instanceof CustomerNotificationsApiError
          ? err.message
          : "Unable to mark all as read.",
      );
    } finally {
      setBusy(false);
    }
  };

  const unread = rows.filter((row) => !row.is_read).length;

  return (
    <StorefrontShell>
      <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <nav aria-label="Breadcrumb" className="mb-6">
          <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            <li>
              <Link href="/account" className="font-medium transition hover:text-[#8b6914]">
                My Account
              </Link>
            </li>
            <li aria-hidden>/</li>
            <li className="font-semibold text-zinc-900">Notifications</li>
          </ol>
        </nav>

        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="font-display text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl">
              Notification Center
            </h1>
            <p className="mt-1 text-sm text-zinc-600">
              Order, payment, warehouse, and shipment updates.
              {unread > 0 ? ` ${unread} unread.` : ""}
            </p>
          </div>
          {unread > 0 ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => void onMarkAll()}
              className="rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
              Mark all read
            </button>
          ) : null}
        </div>

        {!isReady || loading ? (
          <AccountPageSkeleton />
        ) : error ? (
          <p className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {error}
          </p>
        ) : rows.length === 0 ? (
          <p className="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-600">
            No notifications yet.
          </p>
        ) : (
          <ul className="divide-y divide-zinc-200 border border-zinc-200 bg-white">
            {rows.map((row) => (
              <li
                key={row.id}
                className={`px-4 py-4 sm:px-5 ${row.is_read ? "bg-white" : "bg-amber-50/40"}`}
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-zinc-900">{row.title}</p>
                    <p className="mt-1 text-sm text-zinc-600">{row.message}</p>
                    <p className="mt-2 text-xs text-zinc-400">
                      {row.event_type ?? row.type}
                      {row.created_at ? ` · ${formatWhen(row.created_at)}` : ""}
                    </p>
                  </div>
                  {!row.is_read ? (
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void onMarkRead(row.id)}
                      className="shrink-0 text-sm font-medium text-[#8b6914] hover:underline disabled:opacity-50"
                    >
                      Mark read
                    </button>
                  ) : (
                    <span className="shrink-0 text-xs text-zinc-400">Read</span>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </StorefrontShell>
  );
}
