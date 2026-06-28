"use client";

import { useCallback, useEffect, useState } from "react";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import type { Delivery } from "@/lib/delivery/types";
import { DELIVERY_STATUS } from "@/lib/delivery/types";
import { fetchDelivery } from "@/lib/delivery/delivery-api";
import { DELIVERY_STATUS_LABELS, DELIVERIES_UPDATED_EVENT } from "@/lib/delivery/delivery-labels";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";

interface AdminDeliveryPanelProps {
  order: Order;
}

function formatStageTime(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

export function AdminDeliveryPanel({ order }: AdminDeliveryPanelProps) {
  const {
    updateOrderStatus,
    assignDeliveryDriver,
    markOrderShipped,
    markOrderDelivered,
  } = useAdminOrders();

  const [delivery, setDelivery] = useState<Delivery | null>(null);
  const [driverName, setDriverName] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [isBusy, setIsBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadDelivery = useCallback(async () => {
    setIsLoading(true);
    try {
      const record = await fetchDelivery(order.id);
      setDelivery(record);
      if (record?.assignedDriver) {
        setDriverName(record.assignedDriver);
      }
    } catch {
      setDelivery(null);
    } finally {
      setIsLoading(false);
    }
  }, [order.id]);

  useEffect(() => {
    void loadDelivery();
  }, [loadDelivery, order.updatedAt, order.status]);

  useEffect(() => {
    const onUpdated = () => {
      void loadDelivery();
    };
    window.addEventListener(DELIVERIES_UPDATED_EVENT, onUpdated);
    return () => window.removeEventListener(DELIVERIES_UPDATED_EVENT, onUpdated);
  }, [loadDelivery]);

  const runAction = async (action: () => Promise<void> | void) => {
    setError(null);
    setIsBusy(true);
    try {
      await action();
      await loadDelivery();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Action failed.");
    } finally {
      setIsBusy(false);
    }
  };

  const canStartDelivery =
    order.status === ORDER_STATUS.PROCESSING ||
    order.status === ORDER_STATUS.PACKED ||
    order.status === ORDER_STATUS.SHIPPED ||
    order.status === ORDER_STATUS.IN_TRANSIT;

  if (!canStartDelivery && !delivery) {
    return null;
  }

  if (isLoading && !delivery) {
    return (
      <section className="admin-card p-5 sm:p-6">
        <div className="h-24 animate-pulse rounded-xl bg-zinc-50" aria-busy="true" />
      </section>
    );
  }

  const showAssign =
    delivery &&
    delivery.status !== DELIVERY_STATUS.DELIVERED &&
    order.status !== ORDER_STATUS.DELIVERED;

  const showShip =
    delivery?.status === DELIVERY_STATUS.PACKED ||
    (order.status === ORDER_STATUS.PACKED && !delivery);

  const showDeliver =
    delivery?.status === DELIVERY_STATUS.SHIPPED ||
    delivery?.status === DELIVERY_STATUS.IN_TRANSIT ||
    order.status === ORDER_STATUS.SHIPPED ||
    order.status === ORDER_STATUS.IN_TRANSIT;

  return (
    <section className="admin-card p-5 sm:p-6">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-zinc-900">Delivery & logistics</h2>
          <p className="mt-1 text-xs text-zinc-500">
            Manage warehouse handoff, driver assignment, and delivery milestones.
          </p>
        </div>
        {delivery ? (
          <span className="inline-flex rounded-md bg-[#c9a227]/15 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-[#8b6914]">
            {DELIVERY_STATUS_LABELS[delivery.status]}
          </span>
        ) : null}
      </div>

      {delivery ? (
        <dl className="mt-4 grid gap-3 rounded-xl border border-zinc-100 bg-zinc-50/80 p-4 sm:grid-cols-2">
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-zinc-500">
              Delivery ID
            </dt>
            <dd className="mt-1 font-mono text-xs text-zinc-800">{delivery.deliveryId}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-zinc-500">
              Assigned driver
            </dt>
            <dd className="mt-1 text-sm font-medium text-zinc-900">
              {delivery.assignedDriver ?? "Not assigned"}
            </dd>
          </div>
        </dl>
      ) : null}

      {delivery && delivery.stageTimestamps.length > 0 ? (
        <ul className="mt-4 space-y-2">
          {delivery.stageTimestamps.map((stage) => (
            <li
              key={`${stage.status}-${stage.timestamp}`}
              className="flex items-center justify-between gap-3 text-xs"
            >
              <span className="font-semibold text-zinc-700">
                {DELIVERY_STATUS_LABELS[stage.status]}
              </span>
              <span className="text-zinc-500">{formatStageTime(stage.timestamp)}</span>
            </li>
          ))}
        </ul>
      ) : null}

      <div className="mt-4 space-y-3">
        {!delivery && order.status === ORDER_STATUS.PROCESSING ? (
          <ActionButton
            label="Mark as Packed"
            description="Create delivery record and prepare for shipment"
            disabled={isBusy}
            onClick={() =>
              runAction(async () => {
                updateOrderStatus(order.id, ORDER_STATUS.PACKED);
              })
            }
          />
        ) : null}

        {showAssign ? (
          <div className="space-y-2">
            <label htmlFor={`driver-${order.id}`} className="text-xs font-semibold text-zinc-600">
              Assign delivery driver
            </label>
            <div className="flex gap-2">
              <input
                id={`driver-${order.id}`}
                type="text"
                value={driverName}
                onChange={(event) => setDriverName(event.target.value)}
                placeholder="Driver name"
                className="flex-1 rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-900 outline-none focus:border-[#c9a227]"
              />
              <button
                type="button"
                disabled={isBusy || !driverName.trim()}
                onClick={() =>
                  runAction(async () => {
                    await assignDeliveryDriver(order.id, driverName.trim());
                  })
                }
                className="shrink-0 rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:opacity-50"
              >
                Assign
              </button>
            </div>
          </div>
        ) : null}

        {showShip ? (
          <ActionButton
            label="Mark as Shipped"
            description="Start tracking journey — auto in-transit simulation follows"
            disabled={isBusy}
            onClick={() => runAction(async () => markOrderShipped(order.id))}
          />
        ) : null}

        {showDeliver && order.status !== ORDER_STATUS.DELIVERED ? (
          <ActionButton
            label="Mark as Delivered"
            description="Finalize delivery and complete the order"
            disabled={isBusy}
            variant="success"
            onClick={() => runAction(async () => markOrderDelivered(order.id))}
          />
        ) : null}
      </div>

      {error ? <p className="mt-3 text-xs font-medium text-red-600">{error}</p> : null}
    </section>
  );
}

function ActionButton({
  label,
  description,
  onClick,
  disabled,
  variant = "primary",
}: {
  label: string;
  description: string;
  onClick: () => void;
  disabled?: boolean;
  variant?: "primary" | "success";
}) {
  const styles =
    variant === "success"
      ? "bg-emerald-600 text-white hover:bg-emerald-700"
      : "bg-[#c9a227] text-zinc-900 hover:bg-[#e8c547]";

  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={`w-full rounded-lg px-4 py-3 text-left transition disabled:opacity-50 ${styles}`}
    >
      <span className="block text-sm font-semibold">{label}</span>
      <span
        className={`mt-0.5 block text-xs ${variant === "success" ? "text-emerald-100" : "text-zinc-700/80"}`}
      >
        {description}
      </span>
    </button>
  );
}
