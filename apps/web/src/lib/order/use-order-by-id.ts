"use client";

import { useEffect, useRef, useState } from "react";
import type { Order } from "@/lib/types/order";
import { ORDER_TRACKING_POLL_MS } from "@/lib/order/constants";
import { ORDERS_STORAGE_KEY, ORDERS_UPDATED_EVENT } from "@/lib/payment/order-storage";
import { paymentService } from "@/lib/payment/PaymentService";

type UseOrderByIdOptions = {
  /** Listen for order updates (e.g. track-order). Off for confirmation pages. */
  subscribe?: boolean;
  /** Poll local storage for admin-driven status changes. */
  poll?: boolean;
};

export function useOrderById(orderId: string, options: UseOrderByIdOptions = {}) {
  const { subscribe = false, poll = false } = options;
  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const orderIdRef = useRef(orderId);

  orderIdRef.current = orderId;

  useEffect(() => {
    setIsLoading(true);
    setOrder(paymentService.resolveOrder(orderId));
    setIsLoading(false);
  }, [orderId]);

  useEffect(() => {
    if (!subscribe) {
      return;
    }

    const refresh = () => {
      setOrder(paymentService.resolveOrder(orderIdRef.current));
    };

    const onStorage = (event: StorageEvent) => {
      if (event.key === ORDERS_STORAGE_KEY) {
        refresh();
      }
    };

    const onOrdersUpdated = () => refresh();

    window.addEventListener("storage", onStorage);
    window.addEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);

    let intervalId: ReturnType<typeof setInterval> | undefined;
    if (poll) {
      intervalId = setInterval(refresh, ORDER_TRACKING_POLL_MS);
    }

    return () => {
      window.removeEventListener("storage", onStorage);
      window.removeEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);
      if (intervalId) {
        clearInterval(intervalId);
      }
    };
  }, [subscribe, poll, orderId]);

  return { order, isLoading };
}

/** Loads an order snapshot once — for order confirmation (no polling or subscriptions). */
export function useOrderSnapshot(orderId: string) {
  return useOrderById(orderId, { subscribe: false });
}
