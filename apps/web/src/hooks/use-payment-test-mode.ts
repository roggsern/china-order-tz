"use client";

import { useEffect, useState } from "react";
import type { PaymentConfigResponse } from "@/lib/payment/server/types";
import { logPaymentEvent } from "@/lib/payment/payment-logger";

type PaymentTestModeState = {
  isLoading: boolean;
  testMode: boolean;
  simulateEnabled: boolean;
};

const DEFAULT_STATE: PaymentTestModeState = {
  isLoading: true,
  testMode: false,
  simulateEnabled: false,
};

export function usePaymentTestMode(): PaymentTestModeState {
  const [state, setState] = useState<PaymentTestModeState>(DEFAULT_STATE);

  useEffect(() => {
    let cancelled = false;

    async function loadConfig() {
      try {
        const response = await fetch("/api/payments/config", { cache: "no-store" });
        if (!response.ok) {
          throw new Error("Unable to load payment config.");
        }

        const config = (await response.json()) as PaymentConfigResponse;
        if (cancelled) {
          return;
        }

        logPaymentEvent("config.loaded", config);
        setState({
          isLoading: false,
          testMode: config.testMode,
          simulateEnabled: config.simulateEnabled,
        });
      } catch {
        if (!cancelled) {
          setState({ isLoading: false, testMode: false, simulateEnabled: false });
        }
      }
    }

    void loadConfig();
    return () => {
      cancelled = true;
    };
  }, []);

  return state;
}
