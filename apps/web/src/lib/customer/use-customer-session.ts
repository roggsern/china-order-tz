"use client";

import { useEffect, useState } from "react";
import {
  CUSTOMER_SESSION_STORAGE_KEY,
  getCustomerSession,
  type CustomerSession,
} from "@/lib/customer/session";

export function useCustomerSession() {
  const [session, setSession] = useState<CustomerSession | null>(null);
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    setSession(getCustomerSession());
    setIsReady(true);

    const refresh = () => setSession(getCustomerSession());

    const onStorage = (event: StorageEvent) => {
      if (event.key === CUSTOMER_SESSION_STORAGE_KEY) {
        refresh();
      }
    };

    window.addEventListener("storage", onStorage);
    window.addEventListener("customer-session-updated", refresh);
    return () => {
      window.removeEventListener("storage", onStorage);
      window.removeEventListener("customer-session-updated", refresh);
    };
  }, []);

  return {
    session,
    isLoggedIn: Boolean(session),
    isReady,
  };
}
