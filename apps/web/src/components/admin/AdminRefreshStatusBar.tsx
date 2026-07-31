"use client";

function formatSyncTime(date: Date): string {
  return new Intl.DateTimeFormat("en-TZ", {
    hour: "numeric",
    minute: "2-digit",
    second: "2-digit",
  }).format(date);
}

type AdminRefreshStatusBarProps = {
  lastUpdatedAt: Date | null;
  isRefreshing?: boolean;
  onRefresh: () => void;
  policyLabel?: string;
  liveConnected?: boolean;
  liveLabel?: string;
  className?: string;
};

export function AdminRefreshStatusBar({
  lastUpdatedAt,
  isRefreshing = false,
  onRefresh,
  policyLabel,
  liveConnected,
  liveLabel = "Live",
  className = "",
}: AdminRefreshStatusBarProps) {
  const statusLabel = isRefreshing
    ? "Refreshing…"
    : liveConnected
      ? liveLabel
      : lastUpdatedAt
        ? `Updated ${formatSyncTime(lastUpdatedAt)}`
        : "Not synced yet";

  return (
    <div
      className={`flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 shadow-sm ${className}`}
    >
      <div className="flex min-w-0 items-center gap-2">
        <span className="relative flex h-2 w-2 shrink-0" aria-hidden>
          {isRefreshing ? (
            <span className="relative inline-flex h-2 w-2 animate-pulse rounded-full bg-[#c9a227]" />
          ) : liveConnected ? (
            <>
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />
              <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
            </>
          ) : (
            <span className="relative inline-flex h-2 w-2 rounded-full bg-zinc-300" />
          )}
        </span>
        <span className="truncate text-xs font-medium text-zinc-600">{statusLabel}</span>
      </div>

      {policyLabel ? (
        <span className="hidden text-[10px] font-semibold uppercase tracking-wide text-zinc-400 sm:inline">
          {policyLabel}
        </span>
      ) : null}

      <button
        type="button"
        onClick={onRefresh}
        disabled={isRefreshing}
        className="admin-touch-target ml-auto inline-flex min-h-9 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-xs font-semibold text-zinc-800 transition hover:border-zinc-300 disabled:opacity-50"
      >
        {isRefreshing ? "Refreshing…" : "Refresh now"}
      </button>
    </div>
  );
}
