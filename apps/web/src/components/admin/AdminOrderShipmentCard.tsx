"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  AdminFulfillmentApiError,
  fetchAdminFulfillments,
  type AdminFulfillment,
} from "@/lib/api/admin-fulfillments";
import {
  AdminShipmentApiError,
  confirmNegotiatedDelivery,
  createAdminShipment,
  fetchShipmentEligibility,
  type AdminShipment,
  type ShipmentEligibility,
} from "@/lib/api/admin-shipments";

interface AdminOrderShipmentCardProps {
  orderId: string;
}

export function AdminOrderShipmentCard({ orderId }: AdminOrderShipmentCardProps) {
  const [fulfillment, setFulfillment] = useState<AdminFulfillment | null>(null);
  const [eligibility, setEligibility] = useState<ShipmentEligibility | null>(null);
  const [shipment, setShipment] = useState<AdminShipment | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const fulfillments = await fetchAdminFulfillments({ orderId });
      const current = fulfillments[0] ?? null;
      setFulfillment(current);

      if (!current) {
        setEligibility(null);
        setShipment(null);
        return;
      }

      const result = await fetchShipmentEligibility(current.id);
      setEligibility(result);
      setShipment(result.shipment ?? null);
    } catch (err) {
      setError(
        err instanceof AdminShipmentApiError || err instanceof AdminFulfillmentApiError
          ? err.message
          : "Unable to load shipment eligibility.",
      );
    } finally {
      setLoading(false);
    }
  }, [orderId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const handleCreate = async () => {
    if (!fulfillment || busy) return;
    setBusy(true);
    setError(null);
    try {
      const created = await createAdminShipment(fulfillment.id);
      setShipment(created);
      setEligibility((prev) =>
        prev ? { ...prev, eligible: true, shipment: created } : prev,
      );
    } catch (err) {
      setError(
        err instanceof AdminShipmentApiError ? err.message : "Unable to create shipment.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleConfirmNegotiated = async () => {
    if (busy) return;
    setBusy(true);
    setError(null);
    try {
      await confirmNegotiatedDelivery(orderId);
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminShipmentApiError
          ? err.message
          : "Unable to confirm negotiated delivery.",
      );
    } finally {
      setBusy(false);
    }
  };

  const reason = eligibility?.reason ?? null;
  const isAgentOrPickup = reason === "Customer Agent" || reason === "Self Pickup";
  const needsNegotiatedConfirm =
    eligibility?.delivery_type === "negotiated_delivery" &&
    !eligibility.eligible &&
    reason?.includes("admin confirmation");

  return (
    <section className="admin-card p-4 sm:p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-bold text-zinc-900">Shipment</h2>
          <p className="mt-0.5 text-xs text-zinc-500">
            Created only when the company is responsible for transport.
          </p>
        </div>
        <Link href="/admin/shipments" className="text-xs font-semibold text-[#8b6914] hover:underline">
          Queue
        </Link>
      </div>

      {loading ? (
        <p className="mt-4 text-sm text-zinc-500">Loading…</p>
      ) : !fulfillment ? (
        <p className="mt-4 text-sm text-zinc-500">Create fulfillment first.</p>
      ) : shipment ? (
        <dl className="mt-4 space-y-2 text-sm">
          <div className="flex justify-between gap-3">
            <dt className="text-zinc-500">Shipment #</dt>
            <dd className="font-mono text-xs font-semibold">{shipment.shipment_number}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-zinc-500">Mode</dt>
            <dd className="font-semibold uppercase">
              {shipment.transport_mode_label ?? shipment.transport_mode}
            </dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-zinc-500">Status</dt>
            <dd className="font-semibold">{shipment.status_label ?? shipment.status}</dd>
          </div>
        </dl>
      ) : isAgentOrPickup ? (
        <div className="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-700">
          <p className="font-semibold text-zinc-900">Shipment not eligible</p>
          <p className="mt-1">
            Reason: <span className="font-semibold">{reason}</span>
          </p>
          <p className="mt-1 text-xs text-zinc-500">
            Company transport is not created for this delivery option.
          </p>
        </div>
      ) : (
        <div className="mt-4 space-y-3">
          {eligibility && !eligibility.eligible ? (
            <p className="text-sm text-amber-800">{eligibility.reason}</p>
          ) : null}

          {needsNegotiatedConfirm ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => void handleConfirmNegotiated()}
              className="w-full rounded-xl border border-zinc-200 px-3 py-2 text-xs font-semibold text-zinc-800 hover:border-[#c9a227]/50 disabled:opacity-50"
            >
              {busy ? "Confirming…" : "Confirm company will handle negotiated delivery"}
            </button>
          ) : null}

          {eligibility?.eligible ? (
            <button
              type="button"
              disabled={busy}
              onClick={() => void handleCreate()}
              className="w-full rounded-xl bg-zinc-900 px-3 py-2 text-xs font-semibold text-white hover:bg-zinc-800 disabled:opacity-50"
            >
              {busy ? "Creating…" : "Create shipment"}
            </button>
          ) : null}
        </div>
      )}

      {error ? (
        <p className="mt-3 text-xs text-red-600" role="alert">
          {error}
        </p>
      ) : null}
    </section>
  );
}
