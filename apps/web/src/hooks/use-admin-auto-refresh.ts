"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import {
  runDedupedAdminRefresh,
  type AdminAutoRefreshOptions,
} from "@/lib/admin/admin-auto-refresh";
import {
  formatAdminRefreshPolicyLabel,
  getAdminRefreshPolicy,
  isAdminAutoRefreshEnabled,
  resolveAdminRefreshIntervalMs,
  type AdminRefreshPageKey,
} from "@/lib/admin/admin-refresh-policy";

type UseAdminAutoRefreshOptions = {
  page: AdminRefreshPageKey;
  enabled?: boolean;
  onRefresh: (options: AdminAutoRefreshOptions) => void | Promise<void>;
};

type UseAdminAutoRefreshResult = {
  policyLabel: string;
  intervalMs: number | null;
  lastUpdatedAt: Date | null;
  isRefreshing: boolean;
  refreshNow: (options?: AdminAutoRefreshOptions) => Promise<void>;
  markSynced: () => void;
};

/**
 * Reusable admin polling layer. Reuses existing fetch callbacks — no duplicate API clients.
 * Skips duplicate in-flight requests and slows polling when the tab is hidden.
 */
export function useAdminAutoRefresh({
  page,
  enabled = true,
  onRefresh,
}: UseAdminAutoRefreshOptions): UseAdminAutoRefreshResult {
  const [lastUpdatedAt, setLastUpdatedAt] = useState<Date | null>(null);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const inFlightState = useRef({ inFlight: false });
  const onRefreshRef = useRef(onRefresh);
  onRefreshRef.current = onRefresh;

  const policy = getAdminRefreshPolicy(page);
  const intervalMs = resolveAdminRefreshIntervalMs(page, false);
  const policyLabel = formatAdminRefreshPolicyLabel(page);

  const refreshNow = useCallback(async (options: AdminAutoRefreshOptions = {}) => {
    const background = options.background ?? false;
    if (!background) {
      setIsRefreshing(true);
    }

    const ran = await runDedupedAdminRefresh(
      inFlightState.current,
      (opts) => onRefreshRef.current(opts),
      options,
    );

    if (ran) {
      setLastUpdatedAt(new Date());
    }

    if (!background) {
      setIsRefreshing(false);
    }
  }, []);

  const markSynced = useCallback(() => {
    setLastUpdatedAt(new Date());
  }, []);

  useEffect(() => {
    if (!enabled || !isAdminAutoRefreshEnabled(page)) {
      return;
    }

    let intervalId: ReturnType<typeof setInterval> | null = null;

    const schedule = () => {
      if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
      }

      const hidden = typeof document !== "undefined" && document.visibilityState === "hidden";
      const ms = resolveAdminRefreshIntervalMs(page, hidden);
      if (ms === null) {
        return;
      }

      intervalId = setInterval(() => {
        void refreshNow({ background: true });
      }, ms);
    };

    schedule();

    if (typeof document !== "undefined") {
      document.addEventListener("visibilitychange", schedule);
    }

    return () => {
      if (intervalId) {
        clearInterval(intervalId);
      }
      if (typeof document !== "undefined") {
        document.removeEventListener("visibilitychange", schedule);
      }
    };
  }, [enabled, page, refreshNow]);

  return {
    policyLabel,
    intervalMs,
    lastUpdatedAt,
    isRefreshing,
    refreshNow,
    markSynced,
  };
}
