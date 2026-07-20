"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  AdminShipmentApiError,
  fetchAdminShipments,
  fetchAdminShipmentTracking,
  postAdminTrackingEvent,
  type AdminShipment,
  type TrackingTimelineItem,
} from "@/lib/api/admin-shipments";

const STATUS_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/20",
  booked: "bg-blue-50 text-blue-800 ring-blue-600/20",
  in_transit: "bg-indigo-50 text-indigo-800 ring-indigo-600/20",
  arrived: "bg-violet-50 text-violet-800 ring-violet-600/20",
  delivered: "bg-green-50 text-green-800 ring-green-600/20",
  cancelled: "bg-zinc-100 text-zinc-600 ring-zinc-300/40",
};

const EVENT_TYPES = [
  { value: "booked", label: "Booked" },
  { value: "collected", label: "Collected" },
  { value: "departed_origin", label: "Departed origin" },
  { value: "arrived_destination", label: "Arrived destination" },
  { value: "warehouse_received", label: "Warehouse received" },
  { value: "out_for_delivery", label: "Out for delivery" },
  { value: "delivered", label: "Delivered" },
  { value: "cancelled", label: "Cancelled" },
];

export function AdminShipmentQueuePanel() {
  const [rows, setRows] = useState<AdminShipment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState("all");
  const [modeFilter, setModeFilter] = useState("all");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [timeline, setTimeline] = useState<TrackingTimelineItem[]>([]);
  const [busy, setBusy] = useState(false);
  const [eventType, setEventType] = useState("collected");
  const [location, setLocation] = useState("");
  const [description, setDescription] = useState("");

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminShipments({
        status: statusFilter === "all" ? undefined : statusFilter,
        transportMode: modeFilter === "all" ? undefined : modeFilter,
      });
      setRows(data);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminShipmentApiError
          ? err.message
          : "Unable to load shipments.",
      );
    } finally {
      setLoading(false);
    }
  }, [modeFilter, statusFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const openTracking = async (row: AdminShipment) => {
    setSelectedId(row.id);
    setError(null);
    try {
      const payload = await fetchAdminShipmentTracking(row.id);
      setTimeline(payload.timeline ?? []);
      setRows((prev) =>
        prev.map((item) =>
          item.id === row.id
            ? { ...item, status: payload.current_status, status_label: payload.current_status_label }
            : item,
        ),
      );
    } catch (err) {
      setError(
        err instanceof AdminShipmentApiError
          ? err.message
          : "Unable to load tracking.",
      );
    }
  };

  const addEvent = async () => {
    if (!selectedId || busy) return;
    setBusy(true);
    setError(null);
    try {
      const payload = await postAdminTrackingEvent(selectedId, {
        event_type: eventType,
        location: location || null,
        description: description || null,
      });
      setTimeline(payload.timeline ?? []);
      setRows((prev) =>
        prev.map((item) =>
          item.id === selectedId
            ? {
                ...item,
                status: payload.current_status,
                status_label: payload.current_status_label,
              }
            : item,
        ),
      );
      setDescription("");
      setLocation("");
    } catch (err) {
      setError(
        err instanceof AdminShipmentApiError
          ? err.message
          : "Unable to record tracking event.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <header>
        <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#8b6914]">
          Operations
        </p>
        <h1 className="mt-1 text-2xl font-bold tracking-tight text-zinc-900">
          Shipment Queue
        </h1>
        <p className="mt-1 text-sm text-zinc-500">
          Status is derived from tracking events. Add events to advance the timeline.
        </p>
      </header>

      <div className="mt-5 flex flex-wrap gap-2">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All statuses</option>
          <option value="pending">Pending</option>
          <option value="booked">Booked</option>
          <option value="in_transit">In transit</option>
          <option value="arrived">Arrived</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select
          value={modeFilter}
          onChange={(e) => setModeFilter(e.target.value)}
          className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"
        >
          <option value="all">All modes</option>
          <option value="air">Air</option>
          <option value="sea">Sea</option>
          <option value="road">Road</option>
        </select>
        <button
          type="button"
          onClick={() => void reload()}
          className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold"
        >
          Refresh
        </button>
      </div>

      {error ? (
        <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
          {error}
        </p>
      ) : null}

      <div className="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div className="admin-card overflow-hidden">
          {loading ? (
            <div className="p-8 text-sm text-zinc-500">Loading shipments…</div>
          ) : rows.length === 0 ? (
            <div className="p-8 text-center text-sm text-zinc-500">No shipments match these filters.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="border-b border-zinc-100 bg-zinc-50/80 text-[11px] uppercase tracking-wide text-zinc-500">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Shipment</th>
                    <th className="px-4 py-3 font-semibold">Order</th>
                    <th className="px-4 py-3 font-semibold">Customer</th>
                    <th className="px-4 py-3 font-semibold">Mode</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    <th className="px-4 py-3 font-semibold">Tracking</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id} className="border-b border-zinc-50">
                      <td className="px-4 py-3 font-mono text-xs font-semibold">{row.shipment_number}</td>
                      <td className="px-4 py-3">
                        <Link
                          href={`/admin/orders/${encodeURIComponent(row.order_id)}`}
                          className="font-mono text-sm font-semibold hover:text-[#8b6914]"
                        >
                          {row.order?.order_number ?? "—"}
                        </Link>
                      </td>
                      <td className="px-4 py-3 text-zinc-700">{row.order?.customer?.name ?? "—"}</td>
                      <td className="px-4 py-3 text-xs font-semibold uppercase text-zinc-700">
                        {row.transport_mode_label ?? row.transport_mode}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${
                            STATUS_STYLES[row.status] ?? "bg-zinc-50 text-zinc-700 ring-zinc-200"
                          }`}
                        >
                          {row.status_label ?? row.status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <button
                          type="button"
                          onClick={() => void openTracking(row)}
                          className="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-white"
                        >
                          Timeline
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <aside className="admin-card p-4 sm:p-5">
          <h2 className="text-sm font-bold text-zinc-900">Tracking timeline</h2>
          {!selectedId ? (
            <p className="mt-3 text-sm text-zinc-500">Select a shipment to view and add events.</p>
          ) : (
            <>
              <ol className="mt-4 space-y-3">
                {timeline.length === 0 ? (
                  <li className="text-sm text-zinc-500">No events yet.</li>
                ) : (
                  timeline.map((item, index) => (
                    <li key={item.id ?? `${item.event_type}-${index}`} className="border-l-2 border-[#c9a227]/40 pl-3">
                      <p className="text-sm font-semibold text-zinc-900">
                        {item.event_type_label ?? item.event_type}
                      </p>
                      <p className="text-xs text-zinc-500">
                        {item.event_at ? new Date(item.event_at).toLocaleString() : "—"}
                        {item.location ? ` · ${item.location}` : ""}
                      </p>
                      {item.description ? (
                        <p className="mt-0.5 text-sm text-zinc-600">{item.description}</p>
                      ) : null}
                    </li>
                  ))
                )}
              </ol>

              <div className="mt-5 space-y-2 border-t border-zinc-100 pt-4">
                <p className="text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                  Add tracking event
                </p>
                <select
                  value={eventType}
                  onChange={(e) => setEventType(e.target.value)}
                  className="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm"
                >
                  {EVENT_TYPES.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
                <input
                  value={location}
                  onChange={(e) => setLocation(e.target.value)}
                  placeholder="Location"
                  className="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm"
                />
                <textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Description"
                  rows={2}
                  className="w-full rounded-xl border border-zinc-200 px-3 py-2 text-sm"
                />
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void addEvent()}
                  className="w-full rounded-xl bg-zinc-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                >
                  {busy ? "Saving…" : "Record event"}
                </button>
              </div>
            </>
          )}
        </aside>
      </div>
    </div>
  );
}
