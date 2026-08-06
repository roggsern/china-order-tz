"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { buildLoginHref } from "@/lib/auth/return-url";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  createCustomerSupportTicket,
  CustomerSupportApiError,
  CUSTOMER_SUPPORT_CATEGORIES,
  fetchCustomerSupportTicket,
  fetchCustomerSupportTickets,
  isSupportTicketClosed,
  replyCustomerSupportTicket,
  type CustomerSupportTicket,
} from "@/lib/api/customer-support";

function formatWhen(value?: string | null): string {
  if (!value) return "—";
  try {
    return new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(
      new Date(value),
    );
  } catch {
    return value;
  }
}

export function AccountSupportContent() {
  const { isReady, isLoggedIn } = useCustomerSession();
  const [tickets, setTickets] = useState<CustomerSupportTicket[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<CustomerSupportTicket | null>(null);
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mode, setMode] = useState<"list" | "create">("list");
  const [subject, setSubject] = useState("");
  const [category, setCategory] = useState("general");
  const [message, setMessage] = useState("");
  const [reply, setReply] = useState("");
  const [busy, setBusy] = useState(false);

  const reloadList = useCallback(async () => {
    if (!getCustomerApiToken()) {
      setTickets([]);
      setError("Sign in to contact support.");
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const rows = await fetchCustomerSupportTickets();
      setTickets(rows);
    } catch (err) {
      setTickets([]);
      setError(err instanceof CustomerSupportApiError ? err.message : "Unable to load support tickets.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isReady) return;
    void reloadList();
  }, [isReady, isLoggedIn, reloadList]);

  useEffect(() => {
    if (!selectedId) {
      setDetail(null);
      return;
    }

    void (async () => {
      setDetailLoading(true);
      setError(null);
      try {
        const ticket = await fetchCustomerSupportTicket(selectedId);
        setDetail(ticket);
      } catch (err) {
        setDetail(null);
        setError(err instanceof CustomerSupportApiError ? err.message : "Unable to load ticket.");
      } finally {
        setDetailLoading(false);
      }
    })();
  }, [selectedId]);

  async function handleCreate(event: React.FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const created = await createCustomerSupportTicket({ subject, category, message });
      setMode("list");
      setSubject("");
      setMessage("");
      setCategory("general");
      setSelectedId(created.id);
      await reloadList();
    } catch (err) {
      setError(err instanceof CustomerSupportApiError ? err.message : "Unable to create ticket.");
    } finally {
      setBusy(false);
    }
  }

  async function handleReply(event: React.FormEvent) {
    event.preventDefault();
    if (!detail || !reply.trim()) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await replyCustomerSupportTicket(detail.id, reply.trim());
      setDetail(updated);
      setReply("");
      await reloadList();
    } catch (err) {
      setError(err instanceof CustomerSupportApiError ? err.message : "Unable to send reply.");
    } finally {
      setBusy(false);
    }
  }

  if (!isReady || loading) {
    return <AccountPageSkeleton />;
  }

  if (!isLoggedIn || !getCustomerApiToken()) {
    return (
      <div className="rounded-xl border border-zinc-200 bg-white p-6 text-center">
        <p className="text-sm text-zinc-600">Sign in to contact support.</p>
        <Link
          href={buildLoginHref("/account/support")}
          className="mt-3 inline-block text-sm font-semibold text-[#8b6914] hover:underline"
        >
          Sign in
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-zinc-900">Support</h1>
          <p className="mt-1 text-sm text-zinc-500">Create tickets and track responses from our team.</p>
        </div>
        <button
          type="button"
          onClick={() => {
            setMode(mode === "create" ? "list" : "create");
            setError(null);
          }}
          className="rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white hover:bg-[#6d5210]"
        >
          {mode === "create" ? "Back to tickets" : "New ticket"}
        </button>
      </header>

      {error ? (
        <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>
      ) : null}

      {mode === "create" ? (
        <form onSubmit={(e) => void handleCreate(e)} className="space-y-4 rounded-xl border border-zinc-200 bg-white p-5">
          <label className="block text-sm">
            Subject
            <input
              required
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              className="mt-1 w-full rounded border border-zinc-300 px-3 py-2"
            />
          </label>
          <label className="block text-sm">
            Category
            <select
              value={category}
              onChange={(e) => setCategory(e.target.value)}
              className="mt-1 w-full rounded border border-zinc-300 px-3 py-2"
            >
              {CUSTOMER_SUPPORT_CATEGORIES.map((entry) => (
                <option key={entry.value} value={entry.value}>
                  {entry.label}
                </option>
              ))}
            </select>
          </label>
          <label className="block text-sm">
            Message
            <textarea
              required
              rows={5}
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              className="mt-1 w-full rounded border border-zinc-300 px-3 py-2"
            />
          </label>
          <button
            type="submit"
            disabled={busy}
            className="rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
          >
            {busy ? "Submitting…" : "Submit ticket"}
          </button>
        </form>
      ) : (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
          <div className="rounded-xl border border-zinc-200 bg-white">
            {tickets.length === 0 ? (
              <div className="p-6 text-center">
                <p className="text-sm font-medium text-zinc-800">No support tickets yet</p>
                <p className="mt-1 text-sm text-zinc-500">Create a ticket and our team will respond here.</p>
                <button
                  type="button"
                  onClick={() => setMode("create")}
                  className="mt-4 rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white"
                >
                  Create your first ticket
                </button>
              </div>
            ) : (
              <ul className="divide-y divide-zinc-100">
                {tickets.map((ticket) => (
                  <li key={ticket.id}>
                    <button
                      type="button"
                      onClick={() => setSelectedId(ticket.id)}
                      className={`w-full px-4 py-3 text-left hover:bg-zinc-50 ${
                        selectedId === ticket.id ? "bg-amber-50/60" : ""
                      }`}
                    >
                      <p className="text-sm font-medium text-zinc-900">{ticket.subject}</p>
                      <p className="mt-1 text-xs text-zinc-500">
                        {ticket.ticket_number} · {ticket.status_label}
                      </p>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            {detailLoading ? (
              <p className="text-sm text-zinc-500">Loading conversation…</p>
            ) : !detail ? (
              <p className="text-sm text-zinc-500">Select a ticket to view the conversation.</p>
            ) : (
              <div className="space-y-4">
                <div>
                  <p className="text-xs uppercase tracking-wide text-zinc-400">{detail.ticket_number}</p>
                  <h2 className="mt-1 text-lg font-semibold text-zinc-900">{detail.subject}</h2>
                  <p className="mt-1 text-sm text-zinc-500">
                    {detail.status_label} · {detail.category_label}
                  </p>
                </div>
                <ul className="max-h-72 space-y-3 overflow-y-auto border-t border-zinc-100 pt-3">
                  {(detail.messages ?? []).map((msg) => (
                    <li
                      key={msg.id}
                      className={`rounded-lg px-3 py-2 text-sm ${
                        msg.sender_type === "customer" ? "bg-amber-50 text-zinc-800" : "bg-zinc-100 text-zinc-700"
                      }`}
                    >
                      <p className="text-[10px] uppercase tracking-wide text-zinc-400">{msg.sender_type}</p>
                      <p className="mt-1 whitespace-pre-wrap">{msg.message}</p>
                      <p className="mt-1 text-[10px] text-zinc-400">{formatWhen(msg.created_at)}</p>
                    </li>
                  ))}
                </ul>
                {!isSupportTicketClosed(detail.status) ? (
                  <form onSubmit={(e) => void handleReply(e)} className="space-y-2 border-t border-zinc-100 pt-3">
                    <textarea
                      value={reply}
                      onChange={(e) => setReply(e.target.value)}
                      rows={3}
                      placeholder="Write a reply…"
                      className="w-full rounded border border-zinc-300 px-3 py-2 text-sm"
                    />
                    <button
                      type="submit"
                      disabled={busy || !reply.trim()}
                      className="rounded-lg bg-[#8b6914] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                      Send reply
                    </button>
                  </form>
                ) : (
                  <p className="text-sm text-zinc-500">This ticket is closed. Open a new ticket if you need more help.</p>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export function AccountSupportPage() {
  return (
    <StorefrontShell>
      <div className="mx-auto max-w-5xl px-4 py-8">
        <nav aria-label="Breadcrumb" className="mb-6">
          <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            <li>
              <Link href="/account" className="font-medium transition hover:text-[#8b6914]">
                My Account
              </Link>
            </li>
            <li aria-hidden>/</li>
            <li className="font-semibold text-zinc-900">Support</li>
          </ol>
        </nav>
        <AccountSupportContent />
      </div>
    </StorefrontShell>
  );
}
