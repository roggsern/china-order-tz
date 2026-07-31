export type TableSelectionState<TId extends string | number> = {
  selectedIds: Set<TId>;
  allVisibleSelected: boolean;
  selectedCount: number;
};

export function createEmptySelection<TId extends string | number>(): Set<TId> {
  return new Set();
}

export function toggleTableSelection<TId extends string | number>(
  selectedIds: Set<TId>,
  id: TId,
): Set<TId> {
  const next = new Set(selectedIds);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  return next;
}

export function toggleSelectAllVisible<TId extends string | number>(
  selectedIds: Set<TId>,
  visibleIds: readonly TId[],
): Set<TId> {
  const next = new Set(selectedIds);
  const allVisibleSelected =
    visibleIds.length > 0 && visibleIds.every((id) => next.has(id));

  if (allVisibleSelected) {
    visibleIds.forEach((id) => next.delete(id));
  } else {
    visibleIds.forEach((id) => next.add(id));
  }

  return next;
}

export function clearTableSelection<TId extends string | number>(): Set<TId> {
  return new Set();
}

export function pruneSelectionToVisible<TId extends string | number>(
  selectedIds: Set<TId>,
  visibleIds: readonly TId[],
): Set<TId> {
  const visible = new Set(visibleIds);
  const next = new Set<TId>();
  selectedIds.forEach((id) => {
    if (visible.has(id)) {
      next.add(id);
    }
  });
  return next;
}

export function resolveTableSelectionState<TId extends string | number>(
  selectedIds: Set<TId>,
  visibleIds: readonly TId[],
): TableSelectionState<TId> {
  const allVisibleSelected =
    visibleIds.length > 0 && visibleIds.every((id) => selectedIds.has(id));

  return {
    selectedIds,
    allVisibleSelected,
    selectedCount: selectedIds.size,
  };
}
