"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { usePathname } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  ensureStorefrontVisitorIdentity,
  StorefrontVisitorIdentityApiError,
} from "@/lib/api/storefront-visitor-identity";
import { isAdminPath } from "@/lib/checkout/routes";
import {
  loadVisitorIdentity,
  saveVisitorIdentity,
  type StorefrontVisitorIdentity,
} from "@/lib/storefront/visitor-identity";

type VisitorIdentityContextValue = {
  identity: StorefrontVisitorIdentity | null;
  isReady: boolean;
  error: string | null;
  refreshIdentity: () => Promise<void>;
};

const VisitorIdentityContext = createContext<VisitorIdentityContextValue | null>(null);

export function VisitorIdentityProvider({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const [identity, setIdentity] = useState<StorefrontVisitorIdentity | null>(null);
  const [isReady, setIsReady] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const refreshIdentity = useCallback(async () => {
    if (typeof window === "undefined" || isAdminPath(pathname)) {
      setIsReady(true);
      return;
    }

    setError(null);

    try {
      const existing = loadVisitorIdentity();
      const resolved = await ensureStorefrontVisitorIdentity({
        existing,
        token: getCustomerApiToken(),
      });
      saveVisitorIdentity(resolved);
      setIdentity(resolved);
    } catch (err) {
      setError(
        err instanceof StorefrontVisitorIdentityApiError
          ? err.message
          : "Unable to establish visitor identity.",
      );
    } finally {
      setIsReady(true);
    }
  }, [pathname]);

  useEffect(() => {
    setIsReady(false);
    void refreshIdentity();
  }, [refreshIdentity]);

  useEffect(() => {
    const onSessionUpdated = () => {
      void refreshIdentity();
    };

    window.addEventListener("customer-session-updated", onSessionUpdated);
    return () => {
      window.removeEventListener("customer-session-updated", onSessionUpdated);
    };
  }, [refreshIdentity]);

  const value = useMemo<VisitorIdentityContextValue>(
    () => ({
      identity,
      isReady,
      error,
      refreshIdentity,
    }),
    [identity, isReady, error, refreshIdentity],
  );

  return (
    <VisitorIdentityContext.Provider value={value}>{children}</VisitorIdentityContext.Provider>
  );
}

export function useVisitorIdentity(): VisitorIdentityContextValue {
  const context = useContext(VisitorIdentityContext);
  if (!context) {
    throw new Error("useVisitorIdentity must be used within VisitorIdentityProvider.");
  }
  return context;
}
