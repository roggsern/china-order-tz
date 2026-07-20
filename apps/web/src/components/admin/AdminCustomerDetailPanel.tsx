"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  AdminCrmApiError,
  assignAdminCustomerTag,
  createAdminCustomerNote,
  deleteAdminCustomerNote,
  fetchAdminCustomer,
  fetchAdminCustomerNotes,
  fetchAdminCustomerRelated,
  fetchAdminCustomerTags,
  fetchAdminCustomerTimeline,
  rebuildAdminCustomerMetrics,
  removeAdminCustomerTag,
  updateAdminCustomerStatus,
  type AdminCustomer,
  type AdminCustomerNote,
  type AdminCustomerTag,
  type AdminCustomerTimelineEvent,
} from "@/lib/api/admin-crm";

type Tab =
  | "overview"
  | "orders"
  | "payments"
  | "shipments"
  | "returns"
  | "addresses"
  | "timeline"
  | "tags";

function money(value?: string | number | null, currency = "TZS"): string {
  const n = typeof value === "string" ? Number(value) : value ?? 0;
  if (!Number.isFinite(n)) return "—";
  return `${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
}

export function AdminCustomerDetailPanel({ customerId }: { customerId: string }) {
  const [customer, setCustomer] = useState<AdminCustomer | null>(null);
  const [tab, setTab] = useState<Tab>("overview");
  const [related, setRelated] = useState<unknown>(null);
  const [notes, setNotes] = useState<AdminCustomerNote[]>([]);
  const [timeline, setTimeline] = useState<AdminCustomerTimelineEvent[]>([]);
  const [allTags, setAllTags] = useState<AdminCustomerTag[]>([]);
  const [noteBody, setNoteBody] = useState("");
  const [blockReason, setBlockReason] = useState("");
  const [tagId, setTagId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    setError(null);
    try {
      const [c, tags] = await Promise.all([
        fetchAdminCustomer(customerId),
        fetchAdminCustomerTags(),
      ]);
      setCustomer(c);
      setAllTags(tags);
    } catch (err) {
      setError(err instanceof AdminCrmApiError ? err.message : "Unable to load customer.");
    }
  }, [customerId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    if (!customer) return;
    void (async () => {
      try {
        if (tab === "timeline") {
          setTimeline(await fetchAdminCustomerTimeline(customer.id));
        } else if (tab === "tags") {
          setNotes(await fetchAdminCustomerNotes(customer.id));
        } else if (tab !== "overview") {
          const resource = tab === "returns" ? "returns" : tab;
          setRelated(await fetchAdminCustomerRelated(customer.id, resource));
        }
      } catch (err) {
        setError(err instanceof AdminCrmApiError ? err.message : "Unable to load tab data.");
      }
    })();
  }, [tab, customer]);

  const onStatus = async (lifecycle_status: string) => {
    if (!customer) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await updateAdminCustomerStatus(customer.id, {
        lifecycle_status,
        block_reason: lifecycle_status === "blocked" ? blockReason : undefined,
      });
      setCustomer(updated);
      setBlockReason("");
    } catch (err) {
      setError(err instanceof AdminCrmApiError ? err.message : "Status update failed.");
    } finally {
      setBusy(false);
    }
  };

  const m = customer?.metrics;
  const tabs: { id: Tab; label: string }[] = [
    { id: "overview", label: "Overview" },
    { id: "orders", label: "Orders" },
    { id: "payments", label: "Payments" },
    { id: "shipments", label: "Shipments" },
    { id: "returns", label: "Returns & Refunds" },
    { id: "addresses", label: "Addresses" },
    { id: "timeline", label: "Timeline" },
    { id: "tags", label: "Tags & Notes" },
  ];

  if (!customer && !error) {
    return <div className="p-6 text-sm text-zinc-500">Loading customer…</div>;
  }

  return (
    <div className="space-y-5 p-4 md:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link href="/admin/customers" className="text-xs text-zinc-500 hover:text-zinc-300">
            ← Customers
          </Link>
          <h1 className="mt-1 text-xl font-semibold text-zinc-50">
            {customer?.name ?? "Customer"}{" "}
            <span className="text-base font-normal text-[#e8c547]">{customer?.customer_code}</span>
          </h1>
          <p className="mt-1 text-sm text-zinc-500">
            {customer?.email} · {customer?.phone || "No phone"} · {customer?.lifecycle_status}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            disabled={busy}
            onClick={() => void rebuildAdminCustomerMetrics(customerId).then(setCustomer)}
            className="rounded-md border border-zinc-700 px-3 py-1.5 text-sm text-zinc-200"
          >
            Rebuild metrics
          </button>
          {customer?.lifecycle_status === "blocked" ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => void onStatus("active")}
              className="rounded-md bg-emerald-700 px-3 py-1.5 text-sm text-white"
            >
              Unblock
            </button>
          ) : (
            <>
              <button
                type="button"
                disabled={busy}
                onClick={() => void onStatus("dormant")}
                className="rounded-md border border-zinc-700 px-3 py-1.5 text-sm text-zinc-200"
              >
                Mark dormant
              </button>
              <div className="flex gap-1">
                <input
                  value={blockReason}
                  onChange={(e) => setBlockReason(e.target.value)}
                  placeholder="Block reason"
                  className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
                />
                <button
                  type="button"
                  disabled={busy || !blockReason.trim()}
                  onClick={() => void onStatus("blocked")}
                  className="rounded-md bg-red-800 px-3 py-1.5 text-sm text-white disabled:opacity-40"
                >
                  Block
                </button>
              </div>
            </>
          )}
        </div>
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-200">
          {error}
        </div>
      ) : null}

      <div className="flex flex-wrap gap-1 border-b border-zinc-800 pb-2">
        {tabs.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              tab === t.id ? "bg-[#c9a227]/20 text-[#e8c547]" : "text-zinc-400 hover:text-zinc-200"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === "overview" && customer ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {[
            ["Lifetime spend", money(m?.total_spend, m?.currency)],
            ["Total orders", m?.total_orders ?? 0],
            ["Average order value", money(m?.average_order_value, m?.currency)],
            ["Total refunds", money(m?.total_refunds, m?.currency)],
            ["Gross profit generated", money(m?.gross_profit_generated, m?.currency)],
            [
              "Last order",
              m?.last_order_at ? new Date(m.last_order_at).toLocaleString() : "—",
            ],
            ["Loyalty #", customer.loyalty?.loyalty_number ?? "Not enrolled"],
            ["Loyalty points", customer.loyalty?.points_balance ?? "—"],
            ["Loyalty tier", customer.loyalty?.tier?.name ?? "—"],
          ].map(([label, value]) => (
            <div key={String(label)} className="rounded-lg border border-zinc-800 bg-zinc-900/40 px-4 py-3">
              <p className="text-[11px] uppercase tracking-wider text-zinc-500">{label}</p>
              <p className="mt-1 text-lg font-semibold text-zinc-100">{value}</p>
            </div>
          ))}
          <div className="rounded-lg border border-zinc-800 bg-zinc-900/40 px-4 py-3 sm:col-span-2">
            <p className="text-[11px] uppercase tracking-wider text-zinc-500">Profile</p>
            <p className="mt-2 text-sm text-zinc-300">
              Source: {customer.registration_source} · Marketing opt-in:{" "}
              {customer.marketing_opt_in ? "Yes" : "No"}
            </p>
            {customer.block_reason ? (
              <p className="mt-1 text-sm text-red-300">Block reason: {customer.block_reason}</p>
            ) : null}
          </div>
        </div>
      ) : null}

      {tab === "timeline" ? (
        <ul className="space-y-2">
          {timeline.map((ev) => (
            <li key={ev.id} className="rounded-md border border-zinc-800 px-3 py-2">
              <div className="flex justify-between gap-2 text-sm">
                <span className="font-medium text-zinc-100">{ev.title}</span>
                <span className="text-xs text-zinc-500">
                  {new Date(ev.occurred_at).toLocaleString()}
                </span>
              </div>
              <p className="text-xs text-zinc-500">{ev.event_type}</p>
              {ev.description ? <p className="mt-1 text-sm text-zinc-400">{ev.description}</p> : null}
            </li>
          ))}
          {timeline.length === 0 ? <p className="text-sm text-zinc-500">No timeline events yet.</p> : null}
        </ul>
      ) : null}

      {tab === "tags" && customer ? (
        <div className="grid gap-6 lg:grid-cols-2">
          <section>
            <h2 className="mb-2 text-sm font-semibold text-zinc-100">Tags</h2>
            <div className="mb-3 flex flex-wrap gap-2">
              {(customer.tags ?? []).map((t) => (
                <button
                  key={t.id}
                  type="button"
                  onClick={() =>
                    void removeAdminCustomerTag(customer.id, t.id).then(setCustomer)
                  }
                  className="rounded-full border border-zinc-700 px-3 py-1 text-xs text-zinc-200"
                  title="Remove tag"
                >
                  {t.name} ×
                </button>
              ))}
            </div>
            <div className="flex gap-2">
              <select
                value={tagId}
                onChange={(e) => setTagId(e.target.value)}
                className="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
              >
                <option value="">Select tag…</option>
                {allTags.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
              <button
                type="button"
                disabled={!tagId}
                onClick={() =>
                  void assignAdminCustomerTag(customer.id, tagId).then((c) => {
                    setCustomer(c);
                    setTagId("");
                  })
                }
                className="rounded-md bg-[#c9a227] px-3 py-1.5 text-sm font-semibold text-zinc-950"
              >
                Assign
              </button>
            </div>
          </section>
          <section>
            <h2 className="mb-2 text-sm font-semibold text-zinc-100">Internal notes</h2>
            <form
              className="mb-3 space-y-2"
              onSubmit={(e) => {
                e.preventDefault();
                void createAdminCustomerNote(customer.id, noteBody).then((n) => {
                  setNotes((prev) => [n, ...prev]);
                  setNoteBody("");
                });
              }}
            >
              <textarea
                value={noteBody}
                onChange={(e) => setNoteBody(e.target.value)}
                rows={3}
                className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1.5 text-sm text-zinc-100"
                placeholder="Internal note (never shown to customers)"
              />
              <button
                type="submit"
                disabled={!noteBody.trim()}
                className="rounded-md bg-[#c9a227] px-3 py-1.5 text-sm font-semibold text-zinc-950"
              >
                Add note
              </button>
            </form>
            <ul className="space-y-2">
              {notes.map((n) => (
                <li key={n.id} className="rounded-md border border-zinc-800 px-3 py-2 text-sm">
                  <div className="flex justify-between gap-2">
                    <p className="text-zinc-200 whitespace-pre-wrap">{n.body}</p>
                    <button
                      type="button"
                      onClick={() =>
                        void deleteAdminCustomerNote(customer.id, n.id).then(() =>
                          setNotes((prev) => prev.filter((x) => x.id !== n.id)),
                        )
                      }
                      className="text-xs text-red-400"
                    >
                      Delete
                    </button>
                  </div>
                  <p className="mt-1 text-xs text-zinc-500">
                    {n.author?.name ?? "Admin"} ·{" "}
                    {n.created_at ? new Date(n.created_at).toLocaleString() : ""}
                  </p>
                </li>
              ))}
            </ul>
          </section>
        </div>
      ) : null}

      {tab !== "overview" && tab !== "timeline" && tab !== "tags" ? (
        <pre className="max-h-[480px] overflow-auto rounded-lg border border-zinc-800 bg-zinc-950 p-3 text-xs text-zinc-300">
          {JSON.stringify(related, null, 2)}
        </pre>
      ) : null}
    </div>
  );
}
