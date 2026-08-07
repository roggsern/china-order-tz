"use client";

import type { ReactNode } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";

export type AdminDashboardSectionShellProps = {
  children: ReactNode;
  collapsed?: boolean;
  onToggleCollapsed?: () => void;
  loading?: boolean;
  error?: string | null;
  empty?: boolean;
  emptyMessage?: string;
  emptyTitle?: string;
  actions?: ReactNode;
};

export function AdminDashboardSectionShell({
  children,
  collapsed = false,
  onToggleCollapsed,
  loading = false,
  error = null,
  empty = false,
  emptyMessage = "Data will appear here once activity is recorded for this period.",
  emptyTitle = "Nothing to show yet",
  actions,
}: AdminDashboardSectionShellProps) {
  const showToolbar = onToggleCollapsed || actions;

  return (
    <div className="min-w-0 space-y-2">
      {showToolbar ? (
        <div className="flex flex-wrap items-center justify-end gap-2">
          {actions}
          {onToggleCollapsed ? (
            <button
              type="button"
              onClick={onToggleCollapsed}
              className="admin-touch-target rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:text-zinc-900"
              aria-expanded={!collapsed}
            >
              {collapsed ? "Expand section" : "Collapse section"}
            </button>
          ) : null}
        </div>
      ) : null}

      {error ? (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          {error}
        </div>
      ) : null}

      {collapsed ? (
        <p className="rounded-xl border border-dashed border-zinc-300 bg-white px-4 py-6 text-center text-sm text-zinc-600">
          Section collapsed.
        </p>
      ) : loading ? (
        <div className="space-y-3">
          <div className="h-20 animate-pulse rounded-xl bg-zinc-200/70" />
          <div className="h-20 animate-pulse rounded-xl bg-zinc-200/70" />
        </div>
      ) : empty ? (
        <AdminEmptyState title={emptyTitle} description={emptyMessage} />
      ) : (
        children
      )}
    </div>
  );
}
