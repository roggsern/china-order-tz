"use client";

import { useCallback, useMemo } from "react";
import {
  AdminAsyncSearchSelect,
  type AdminAsyncOption,
} from "@/components/admin/AdminAsyncSearchSelect";
import {
  filterCatalogProductTypesByQuery,
  formatCatalogProductTypeOptionDescription,
} from "@/lib/admin/catalog-product-type-scope";
import type { AdminCatalogProductType } from "@/lib/api/admin-catalog";

type AdminCatalogProductTypeSelectProps = {
  id: string;
  value: string;
  options: AdminCatalogProductType[];
  disabled?: boolean;
  selectedLabel?: string;
  onChange: (typeId: string, type: AdminCatalogProductType | null) => void;
};

const PAGE_SIZE = 50;

export function AdminCatalogProductTypeSelect({
  id,
  value,
  options,
  disabled = false,
  selectedLabel,
  onChange,
}: AdminCatalogProductTypeSelectProps) {
  const selected = useMemo(
    () => options.find((type) => type.id === value) ?? null,
    [options, value],
  );
  const effectiveLabel = selectedLabel ?? selected?.name;

  const loadOptions = useCallback(
    async (query: string, page: number) => {
      const filtered = filterCatalogProductTypesByQuery(options, query);
      const start = (page - 1) * PAGE_SIZE;
      const pageItems = filtered.slice(start, start + PAGE_SIZE);

      return {
        items: pageItems.map(
          (type): AdminAsyncOption => ({
            id: type.id,
            label: type.name,
            description: formatCatalogProductTypeOptionDescription(type),
          }),
        ),
        hasMore: start + PAGE_SIZE < filtered.length,
      };
    },
    [options],
  );

  const reloadKey = useMemo(
    () =>
      `${options.length}:${options[0]?.id ?? ""}:${options[options.length - 1]?.id ?? ""}:${value}`,
    [options, value],
  );

  const emptyMessage =
    options.length === 0
      ? "Select a category first to see product types."
      : "No product types match your search.";

  return (
    <AdminAsyncSearchSelect
      id={id}
      value={value}
      selectedLabel={effectiveLabel}
      disabled={disabled}
      placeholder="Search product types..."
      emptyMessage={emptyMessage}
      debounceMs={0}
      reloadKey={reloadKey}
      loadOptions={loadOptions}
      onChange={(nextId) => {
        if (!nextId) {
          onChange("", null);
          return;
        }
        const nextType = options.find((type) => type.id === nextId) ?? null;
        onChange(nextId, nextType);
      }}
    />
  );
}
