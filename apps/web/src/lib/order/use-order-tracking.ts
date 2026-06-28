"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { Order } from "@/lib/types/order";
import { ORDER_TRACKING_POLL_MS } from "@/lib/order/constants";
import { ORDERS_STORAGE_KEY, ORDERS_UPDATED_EVENT } from "@/lib/payment/order-storage";
import { paymentService } from "@/lib/payment/PaymentService";
import { fetchOrderTracking, type OrderTrackingResponse } from "@/lib/order/tracking-api";
import {
  buildTrackingTimeline,
  type TrackingTimelineStep,
} from "@/lib/order/tracking-status";
import { subscribeOrderTrackingWs } from "@/lib/order/order-tracking-ws";

type UseOrderTrackingResult = {
  order: Order | null;
  tracking: OrderTrackingResponse | null;
  delivery: OrderTrackingResponse["delivery"];
  timeline: TrackingTimelineStep[];
  isLoading: boolean;
  isLive: boolean;
  refresh: () => Promise<void>;
};

function mergeTracking(local: Order | null, remote: OrderTrackingResponse | null): Order | null {
  if (!local && !remote) {
    return null;
  }

  if (!local && remote) {
    return paymentService.resolveOrder(remote.orderId);
  }

  if (local && !remote) {
    return local;
  }

  if (!local || !remote) {
    return local;
  }

  const remoteUpdated = new Date(remote.order.updatedAt).getTime();
  const localUpdated = new Date(local.updatedAt).getTime();

  if (remoteUpdated >= localUpdated) {
    return {
      ...local,
      status: remote.order.status,
      paymentStatus: remote.order.paymentStatus,
      paymentReference: remote.order.paymentReference ?? local.paymentReference,
      updatedAt: remote.order.updatedAt,
      statusHistory: remote.statusHistory,
    };
  }

  return local;
}

export function useOrderTracking(orderId: string): UseOrderTrackingResult {
  const [order, setOrder] = useState<Order | null>(null);
  const [tracking, setTracking] = useState<OrderTrackingResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLive, setIsLive] = useState(false);
  const orderIdRef = useRef(orderId);

  orderIdRef.current = orderId;

  const refresh = useCallback(async () => {
    const local = paymentService.resolveOrder(orderIdRef.current);
    let remote: OrderTrackingResponse | null = null;

    try {
      remote = await fetchOrderTracking(orderIdRef.current);
    } catch {
      remote = null;
    }

    setTracking(remote);
    setOrder(mergeTracking(local, remote));
  }, []);

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      setIsLoading(true);
      await refresh();
      if (!cancelled) {
        setIsLoading(false);
      }
    };

    void load();

    return () => {
      cancelled = true;
    };
  }, [orderId, refresh]);

  useEffect(() => {
    const onStorage = (event: StorageEvent) => {
      if (event.key === ORDERS_STORAGE_KEY) {
        void refresh();
      }
    };

    const onOrdersUpdated = () => {
      void refresh();
    };

    window.addEventListener("storage", onStorage);
    window.addEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);

    const intervalId = setInterval(() => {
      void refresh();
    }, ORDER_TRACKING_POLL_MS);

    const unsubscribeWs = subscribeOrderTrackingWs(orderIdRef.current, {
      onStatusUpdated: () => {
        void refresh();
      },
      onConnected: () => setIsLive(true),
      onDisconnected: () => setIsLive(false),
    });

    return () => {
      window.removeEventListener("storage", onStorage);
      window.removeEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);
      clearInterval(intervalId);
      unsubscribeWs();
    };
  }, [orderId, refresh]);

  const timeline =
    tracking?.timeline ??
    (order ? buildTrackingTimeline(order, tracking?.delivery ?? null) : []);

  return {
    order,
    tracking,
    delivery: tracking?.delivery ?? null,
    timeline,
    isLoading,
    isLive,
    refresh,
  };
}
