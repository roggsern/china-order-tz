"use client";

import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";

function formatSyncTime(date: Date): string {
  return new Intl.DateTimeFormat("en-TZ", {
    hour: "numeric",
    minute: "2-digit",
    second: "2-digit",
  }).format(date);
}

export function AdminLiveIndicator() {
  const { wsConnected, isHydrated, lastSyncedAt, realtimeTransport } = useAdminOrders();

  if (!isHydrated) {
    return null;
  }

  const connected = wsConnected;
  const isPolling = realtimeTransport === "polling";

  const label = connected ? (isPolling ? "Syncing" : "Live") : isPolling ? "Offline" : "Reconnecting";
  const title = connected
    ? lastSyncedAt
      ? `${isPolling ? "Polling" : "Live"} — last update ${formatSyncTime(lastSyncedAt)}`
      : isPolling
        ? "Polling order API for updates"
        : "WebSocket connected"
    : isPolling
      ? "Unable to reach order API"
      : "Reconnecting to live order feed…";

  return (
    <div
      className={`hidden items-center gap-2 rounded-full px-2.5 py-1 sm:flex ${
        connected
          ? "border border-emerald-500/30 bg-emerald-500/10"
          : "border border-amber-500/30 bg-amber-500/10"
      }`}
      title={title}
    >
      <span className="relative flex h-2 w-2" aria-hidden>
        {connected ? (
          <>
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />
            <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
          </>
        ) : (
          <span className="relative inline-flex h-2 w-2 animate-pulse rounded-full bg-amber-400" />
        )}
      </span>
      <span
        className={`text-[10px] font-bold uppercase tracking-wider ${
          connected ? "text-emerald-400" : "text-amber-400"
        }`}
      >
        {label}
      </span>
    </div>
  );
}
