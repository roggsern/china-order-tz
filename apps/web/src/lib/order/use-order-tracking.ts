"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { CustomerTrackingPayload } from "@/lib/api/customer-tracking";
import { ORDER_TRACKING_POLL_MS } from "@/lib/order/constants";
import { loadLiveOrderTracking } from "@/lib/order/order-tracking-loader";
import type { Order } from "@/lib/types/order";

type UseOrderTrackingResult = {
  order: Order | null;
  tracking: CustomerTrackingPayload | null;
  isLoading: boolean;
  isLive: boolean;
  needsAuth: boolean;
  refresh: () => Promise<void>;
};

export function useOrderTracking(orderId: string): UseOrderTrackingResult {
  const [order, setOrder] = useState<Order | null>(null);
  const [tracking, setTracking] = useState<CustomerTrackingPayload | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isLive, setIsLive] = useState(false);
  const [needsAuth, setNeedsAuth] = useState(false);
  const orderIdRef = useRef(orderId);
  const hasLoadedRef = useRef(false);

  orderIdRef.current = orderId;

  const refresh = useCallback(async (options?: { silent?: boolean }) => {
    const silent = options?.silent ?? hasLoadedRef.current;

    if (!silent) {
      setIsLoading(true);
    }

    const result = await loadLiveOrderTracking(orderIdRef.current);

    setNeedsAuth(result.needsAuth);
    setOrder(result.order);
    setTracking(result.tracking);
    setIsLive(result.tracking !== null);
    hasLoadedRef.current = true;

    if (!silent) {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    hasLoadedRef.current = false;
    void refresh();
  }, [orderId, refresh]);

  useEffect(() => {
    if (needsAuth || !order) {
      return;
    }

    const intervalId = setInterval(() => {
      void refresh({ silent: true });
    }, ORDER_TRACKING_POLL_MS);

    return () => {
      clearInterval(intervalId);
    };
  }, [needsAuth, order, refresh]);

  return {
    order,
    tracking,
    isLoading,
    isLive,
    needsAuth,
    refresh,
  };
}
