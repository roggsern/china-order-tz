"use client";

import { useCallback, useEffect, useState } from "react";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerTrackingApiError,
  fetchCustomerOrderTracking,
  type CustomerTrackingPayload,
} from "@/lib/api/customer-tracking";

interface OrderShipmentTrackingPanelProps {
  orderNumber: string;
}

export function OrderShipmentTrackingPanel({ orderNumber }: OrderShipmentTrackingPanelProps) {
  const [data, setData] = useState<CustomerTrackingPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    const token = getCustomerApiToken();
    if (!token) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      setData(await fetchCustomerOrderTracking(orderNumber, token));
    } catch (err) {
      setData(null);
      setError(
        err instanceof CustomerTrackingApiError
          ? err.message
          : "Unable to load tracking.",
      );
    } finally {
      setLoading(false);
    }
  }, [orderNumber]);

  useEffect(() => {
    void reload();
  }, [reload]);

  if (loading) {
    return (
      <section className="rounded-3xl border border-zinc-200/70 bg-white p-5 sm:p-7">
        <p className="text-sm text-zinc-500">Loading tracking…</p>
      </section>
    );
  }

  if (!data) {
    return error ? (
      <section className="rounded-3xl border border-zinc-200/70 bg-white p-5 sm:p-7">
        <p className="text-sm text-red-700">{error}</p>
      </section>
    ) : null;
  }

  return (
    <section
      aria-labelledby="shipment-tracking-heading"
      className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
    >
      <h2 id="shipment-tracking-heading" className="text-lg font-bold text-zinc-900">
        Shipment tracking
      </h2>
      <p className="mt-1 text-sm text-zinc-500">
        Current status:{" "}
        <span className="font-semibold text-zinc-900">
          {data.current_status_label ?? data.current_status}
        </span>
      </p>

      {data.shipment_summary ? (
        <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs uppercase tracking-wide text-zinc-500">Shipment</dt>
            <dd className="font-mono font-semibold">{data.shipment_summary.shipment_number}</dd>
          </div>
          {data.shipment_summary.carrier_name ? (
            <div>
              <dt className="text-xs uppercase tracking-wide text-zinc-500">Carrier</dt>
              <dd className="font-medium">{data.shipment_summary.carrier_name}</dd>
            </div>
          ) : null}
        </dl>
      ) : null}

      <ol className="mt-6 space-y-4">
        {data.timeline.map((item, index) => {
          const title =
            item.event_type_label ??
            item.event_type ??
            item.step ??
            "Update";
          const time = item.event_at ?? item.completed_at;

          return (
            <li
              key={item.id ?? `${title}-${index}`}
              className="border-l-2 border-[#c9a227]/35 pl-4"
            >
              <p className="text-sm font-semibold text-zinc-900">{title}</p>
              <p className="text-xs text-zinc-500">
                {time ? new Date(time).toLocaleString() : "—"}
                {item.location ? ` · ${item.location}` : ""}
              </p>
              {item.description ? (
                <p className="mt-1 text-sm text-zinc-600">{item.description}</p>
              ) : null}
            </li>
          );
        })}
      </ol>
    </section>
  );
}
