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
  categoryId: string;
  subcategoryId: string;
  disabled?: boolean;
  onChange: (selection: CategoryTreeSelection) => void;
};

export function AdminCategoryTreeSelect({
  id,
  categories,
  departmentId,
  categoryId,
  subcategoryId,
  disabled = false,
  onChange,
}: AdminCategoryTreeSelectProps) {
  const scoped = useMemo(() => {
    if (!departmentId) return categories;
    return categories.filter((item) => item.departmentId === departmentId);
  }, [categories, departmentId]);

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
      disabled={disabled || Boolean(departmentId !== undefined && !departmentId)}
      placeholder={
        departmentId !== undefined && !departmentId
          ? "Select a department first"
          : "Search categories…"
      }
      emptyMessage="No categories match your search."
      reloadKey={`${departmentId ?? "all"}:${scoped.length}`}
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
