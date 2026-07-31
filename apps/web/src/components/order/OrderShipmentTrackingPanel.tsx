"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerTrackingApiError,
  fetchCustomerOrderTracking,
  type CustomerTrackingPayload,
} from "@/lib/api/customer-tracking";
import { ORDER_TRACKING_POLL_MS } from "@/lib/order/constants";
import {
  buildCustomerProgressDisplayTimeline,
  isAgentDeliveryProgress,
  parseCustomerOrderProgress,
} from "@/lib/order/customer-progress";
import { OrderTrackingStepper } from "./OrderTrackingStepper";

interface OrderShipmentTrackingPanelProps {
  orderNumber: string;
}

export function OrderShipmentTrackingPanel({ orderNumber }: OrderShipmentTrackingPanelProps) {
  const [data, setData] = useState<CustomerTrackingPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const hasLoadedRef = useRef(false);

  const reload = useCallback(async (options?: { silent?: boolean }) => {
    const token = getCustomerApiToken();
    if (!token) {
      setLoading(false);
      return;
    }

    const silent = options?.silent ?? hasLoadedRef.current;
    if (!silent) {
      setLoading(true);
    }
    setError(null);
    try {
      setData(await fetchCustomerOrderTracking(orderNumber, token));
      hasLoadedRef.current = true;
    } catch (err) {
      if (!silent) {
        setData(null);
      }
      setError(
        err instanceof CustomerTrackingApiError
          ? err.message
          : "Unable to load tracking.",
      );
    } finally {
      if (!silent) {
        setLoading(false);
      }
    }
  }, [orderNumber]);

  useEffect(() => {
    hasLoadedRef.current = false;
    void reload();
  }, [reload]);

  useEffect(() => {
    const token = getCustomerApiToken();
    if (!token) {
      return;
    }

    const intervalId = setInterval(() => {
      void reload({ silent: true });
    }, ORDER_TRACKING_POLL_MS);

    return () => {
      clearInterval(intervalId);
    };
  }, [orderNumber, reload]);

  const progress = useMemo(
    () => (data ? parseCustomerOrderProgress(data.progress) : null),
    [data],
  );

  const displayTimeline = useMemo(() => {
    if (!progress) {
      return [];
    }

    return buildCustomerProgressDisplayTimeline(progress);
  }, [progress]);

  const isAgentDelivery = progress ? isAgentDeliveryProgress(progress) : false;
  const panelTitle = isAgentDelivery ? "Order progress" : "Shipment tracking";
  const panelHeadingId = isAgentDelivery ? "order-progress-heading" : "shipment-tracking-heading";

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

  const currentLabel = progress?.current_label ?? data.current_status_label ?? data.current_status;

  return (
    <section
      aria-labelledby={panelHeadingId}
      className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
    >
      <h2 id={panelHeadingId} className="text-lg font-bold text-zinc-900">
        {panelTitle}
      </h2>
      <p className="mt-1 text-sm text-zinc-500">
        Current status:{" "}
        <span className="font-semibold text-zinc-900">{currentLabel}</span>
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

      {displayTimeline.length > 0 ? (
        <div className="mt-6">
          <OrderTrackingStepper timeline={displayTimeline} tone="light" />
        </div>
      ) : (
        <p className="mt-6 text-sm text-zinc-500">Tracking updates will appear here.</p>
      )}
    </section>
  );
}
