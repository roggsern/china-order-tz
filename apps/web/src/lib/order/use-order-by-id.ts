"use client";

import { useEffect, useRef, useState } from "react";
import type { Order } from "@/lib/types/order";
import { ORDERS_STORAGE_KEY, ORDERS_UPDATED_EVENT } from "@/lib/payment/order-storage";
import { paymentService } from "@/lib/payment/PaymentService";

type UseOrderByIdOptions = {
  /** Listen for order updates (e.g. track-order). Off for confirmation pages. */
  subscribe?: boolean;
};

export function useOrderById(orderId: string, options: UseOrderByIdOptions = {}) {
  const { subscribe = false } = options;
  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const orderIdRef = useRef(orderId);

  orderIdRef.current = orderId;

  useEffect(() => {
    setOrder(paymentService.getOrderById(orderId));
    setIsLoading(false);
  }, [orderId]);

  useEffect(() => {
    if (!subscribe) {
      return;
    }

    const refresh = () => {
      setOrder(paymentService.getOrderById(orderIdRef.current));
    };

    const onStorage = (event: StorageEvent) => {
      if (event.key === ORDERS_STORAGE_KEY) {
        refresh();
      }
    };

    const onOrdersUpdated = () => refresh();

    window.addEventListener("storage", onStorage);
    window.addEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);
    return () => {
      window.removeEventListener("storage", onStorage);
      window.removeEventListener(ORDERS_UPDATED_EVENT, onOrdersUpdated);
    };
  }, [subscribe, orderId]);

  return { order, isLoading };
}

/** Loads an order snapshot once — for order confirmation (no polling or subscriptions). */
export function useOrderSnapshot(orderId: string) {
  return useOrderById(orderId, { subscribe: false });
}
