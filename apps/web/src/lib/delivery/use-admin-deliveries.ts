"use client";

import { useCallback, useEffect, useState } from "react";
import type { Delivery } from "@/lib/delivery/types";
import { fetchDeliveries } from "@/lib/delivery/delivery-api";
import { DELIVERIES_UPDATED_EVENT } from "@/lib/delivery/delivery-labels";

type UseAdminDeliveriesResult = {
  deliveries: Delivery[];
  isLoading: boolean;
  refresh: () => Promise<void>;
};

export function useAdminDeliveries(activeOnly = true): UseAdminDeliveriesResult {
  const [deliveries, setDeliveries] = useState<Delivery[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const refresh = useCallback(async () => {
    try {
      const next = await fetchDeliveries(activeOnly);
      setDeliveries(next);
    } catch {
      setDeliveries([]);
    } finally {
      setIsLoading(false);
    }
  }, [activeOnly]);

  useEffect(() => {
    void refresh();

    const onDeliveriesUpdated = () => {
      void refresh();
    };

    window.addEventListener(DELIVERIES_UPDATED_EVENT, onDeliveriesUpdated);

    const intervalId = setInterval(() => {
      void refresh();
    }, 20_000);

    return () => {
      window.removeEventListener(DELIVERIES_UPDATED_EVENT, onDeliveriesUpdated);
      clearInterval(intervalId);
    };
  }, [refresh]);

  return { deliveries, isLoading, refresh };
}
