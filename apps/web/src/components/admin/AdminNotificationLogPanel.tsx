"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminNotificationsApiError,
  fetchAdminNotificationLog,
  type AdminNotificationLogRow,
} from "@/lib/api/admin-notifications";

function formatWhen(value?: string | null): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export function AdminNotificationLogPanel() {
  const [rows, setRows] = useState<AdminNotificationLogRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [channel, setChannel] = useState("all");
  const [status, setStatus] = useState("all");

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminNotificationLog({
        channel: channel === "all" ? undefined : channel,
        status: status === "all" ? undefined : status,
      });
      setRows(data);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminNotificationsApiError
          ? err.message
          : "Unable to load notification log.",
      );
    } finally {
      setLoading(false);
    }
  }, [channel, status]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-semibold text-zinc-900">Notification Log</h1>
        <p className="mt-1 text-sm text-zinc-600">
          Delivery attempts across in-app, email, WhatsApp, SMS, and push.
        </p>
      </div>

      <div className="flex flex-wrap gap-3">
        <select
          value={channel}
          onChange={(e) => setChannel(e.target.value)}
          className="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All channels</option>
          <option value="in_app">In-App</option>
          <option value="email">Email</option>
          <option value="whatsapp">WhatsApp</option>
          <option value="sms">SMS</option>
          <option value="push">Push</option>
        </select>
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All statuses</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="sent">Sent</option>
          <option value="failed">Failed</option>
          <option value="read">Read</option>
        </select>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50"
        >
          Refresh
        </button>
      </div>

      {error ? (
        <p className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </p>
      ) : null}

      <div className="overflow-x-auto rounded-md border border-zinc-200 bg-white">
        <table className="min-w-full text-left text-sm">
          <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
            <tr>
              <th className="px-3 py-2 font-medium">Event</th>
              <th className="px-3 py-2 font-medium">Customer</th>
              <th className="px-3 py-2 font-medium">Channel</th>
              <th className="px-3 py-2 font-medium">Status</th>
              <th className="px-3 py-2 font-medium">Provider</th>
              <th className="px-3 py-2 font-medium">Created</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {loading ? (
              <tr>
                <td colSpan={6} className="px-3 py-8 text-center text-zinc-500">
                  Loading…
                </td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-3 py-8 text-center text-zinc-500">
                  No notification deliveries logged.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id} className="align-top">
                  <td className="px-3 py-3">
                    <p className="font-medium text-zinc-900">{row.title}</p>
                    <p className="mt-0.5 text-xs text-zinc-500">{row.event_type}</p>
                    {row.error_message ? (
                      <p className="mt-1 text-xs text-red-600">{row.error_message}</p>
                    ) : null}
                  </td>
                  <td className="px-3 py-3 text-zinc-700">
                    {row.customer?.email ?? row.customer_id ?? "—"}
                  </td>
                  <td className="px-3 py-3">{row.channel ?? "—"}</td>
                  <td className="px-3 py-3">{row.status ?? "—"}</td>
                  <td className="px-3 py-3">{row.provider ?? "—"}</td>
                  <td className="px-3 py-3 text-zinc-500">{formatWhen(row.created_at)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
