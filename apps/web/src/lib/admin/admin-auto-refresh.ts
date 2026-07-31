import type { AdminRefreshPageKey } from "@/lib/admin/admin-refresh-policy";
import {
  isAdminAutoRefreshEnabled,
  resolveAdminRefreshIntervalMs,
} from "@/lib/admin/admin-refresh-policy";

export type AdminAutoRefreshOptions = {
  /** Background tick — avoid full-page loading skeletons. */
  background?: boolean;
  /** User clicked manual refresh. */
  manual?: boolean;
};

export type AdminAutoRefreshSchedulerState = {
  inFlight: boolean;
  enabled: boolean;
  page: AdminRefreshPageKey;
  hidden: boolean;
};

/**
 * Returns true when a scheduled refresh tick should be skipped.
 */
export function shouldSkipAutoRefreshTick(state: AdminAutoRefreshSchedulerState): boolean {
  if (!state.enabled || state.inFlight) {
    return true;
  }

  if (!isAdminAutoRefreshEnabled(state.page)) {
    return true;
  }

  return resolveAdminRefreshIntervalMs(state.page, state.hidden) === null;
}

/**
 * Runs refresh once; returns false when deduplicated because a request is in flight.
 */
export async function runDedupedAdminRefresh(
  state: { inFlight: boolean },
  refresh: (options: AdminAutoRefreshOptions) => void | Promise<void>,
  options: AdminAutoRefreshOptions = {},
): Promise<boolean> {
  if (state.inFlight) {
    return false;
  }

  state.inFlight = true;
  try {
    await refresh(options);
    return true;
  } finally {
    state.inFlight = false;
  }
}

/**
 * Captures queue filter context so background refresh reuses current filters/pagination.
 */
export function buildQueueRefreshSnapshot<TFilters extends Record<string, unknown>>(input: {
  filters: TFilters;
  page: number;
  selectedIds: Iterable<string>;
}): {
  filters: TFilters;
  page: number;
  selectedIds: string[];
} {
  return {
    filters: input.filters,
    page: input.page,
    selectedIds: [...input.selectedIds],
  };
}

/**
 * Keeps bulk/table selections when paginated data refreshes.
 */
export function preserveSelectionAfterRefresh(
  previousSelected: Set<string>,
  visibleIds: Iterable<string>,
): Set<string> {
  const visible = new Set(visibleIds);
  const next = new Set<string>();
  for (const id of previousSelected) {
    if (visible.has(id)) {
      next.add(id);
    }
  }
  return next;
}
