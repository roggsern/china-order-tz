"use client";

import { useCallback, useMemo } from "react";
import {
  AdminAsyncSearchSelect,
  type AdminAsyncOption,
} from "@/components/admin/AdminAsyncSearchSelect";
import { matchesAdminSearchTerms } from "@/lib/admin/admin-search-utils";
import type { AdminCategory } from "@/lib/api/admin-catalog";
import {
  buildCategoryTreeOptions,
  mapCategoryTreeSelection,
  resolveCategoryTreeLabel,
  type CategoryTreeSelection,
} from "@/lib/admin/catalog-selector-utils";

type AdminCategoryTreeSelectProps = {
  id: string;
  categories: AdminCategory[];
  departmentId?: string;
  storeId?: string;
  categoryId: string;
  subcategoryId: string;
  disabled?: boolean;
  onChange: (selection: CategoryTreeSelection) => void;
};

export function AdminCategoryTreeSelect({
  id,
  categories,
  departmentId,
  storeId,
  categoryId,
  subcategoryId,
  disabled = false,
  onChange,
}: AdminCategoryTreeSelectProps) {
  const scoped = useMemo(() => {
    if (storeId) {
      return categories.filter((item) => item.storeId === storeId);
    }
    if (departmentId) {
      return categories.filter((item) => item.departmentId === departmentId);
    }
    return categories;
  }, [categories, departmentId, storeId]);

  const scopeBlocked = storeId !== undefined
    ? !storeId
    : departmentId !== undefined
      ? !departmentId
      : false;

  const scopePlaceholder = storeId !== undefined
    ? "Select a store first"
    : departmentId !== undefined
      ? "Select a department first"
      : "Search categories…";

  const selectedLabel = useMemo(
    () => resolveCategoryTreeLabel(scoped, categoryId, subcategoryId),
    [scoped, categoryId, subcategoryId],
  );

  const selectedId = subcategoryId || categoryId;

  const loadOptions = useCallback(
    async (query: string, page: number) => {
      const tree = buildCategoryTreeOptions(scoped).filter((option) =>
        matchesAdminSearchTerms(
          `${option.label} ${option.description ?? ""}`,
          query,
        ),
      );
      const perPage = 40;
      const start = (page - 1) * perPage;
      const slice = tree.slice(start, start + perPage);
      return {
        items: slice,
        hasMore: start + perPage < tree.length,
      };
    },
    [scoped],
  );

  return (
    <AdminAsyncSearchSelect
      id={id}
      value={selectedId}
      selectedLabel={selectedLabel}
      disabled={disabled || scopeBlocked}
      placeholder={scopeBlocked ? scopePlaceholder : "Search categories…"}
      emptyMessage="No categories match your search."
      reloadKey={`${departmentId ?? "all"}:${storeId ?? "all"}:${scoped.length}`}
      loadOptions={loadOptions}
      onChange={(nextId, option) => {
        if (!option) {
          onChange({ categoryId: "", subcategoryId: "" });
          return;
        }
        onChange(mapCategoryTreeSelection(scoped, nextId));
      }}
    />
  );
}

// Re-export option type for callers that need it.
export type { AdminAsyncOption };
