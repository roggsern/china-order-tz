"use client";

import { useCallback, useState } from "react";
import {
  AdminAsyncSearchSelect,
  type AdminAsyncOption,
} from "@/components/admin/AdminAsyncSearchSelect";
import {
  fetchAdminSuppliersPage,
  type AdminSupplier,
} from "@/lib/api/admin-procurement";

type AdminSupplierAsyncSelectProps = {
  id: string;
  value: string;
  selectedLabel?: string;
  disabled?: boolean;
  onChange: (supplierId: string, supplier: AdminSupplier | null) => void;
};

export function AdminSupplierAsyncSelect({
  id,
  value,
  selectedLabel,
  disabled = false,
  onChange,
}: AdminSupplierAsyncSelectProps) {
  const [labelOverride, setLabelOverride] = useState<string | null>(null);

  const loadOptions = useCallback(async (query: string, page: number) => {
    const result = await fetchAdminSuppliersPage({
      search: query || undefined,
      isActive: true,
      page,
      perPage: 20,
    });

    return {
      items: result.items.map(
        (supplier): AdminAsyncOption => ({
          id: supplier.id,
          label: supplier.name,
          description: supplier.code || undefined,
        }),
      ),
      hasMore: result.page < result.lastPage,
    };
  }, []);

  return (
    <AdminAsyncSearchSelect
      id={id}
      value={value}
      selectedLabel={labelOverride ?? selectedLabel}
      disabled={disabled}
      placeholder="Search suppliers…"
      emptyMessage="No suppliers match your search."
      loadOptions={loadOptions}
      onChange={(nextId, option) => {
        setLabelOverride(option?.label ?? null);
        onChange(
          nextId,
          option
            ? ({
                id: option.id,
                name: option.label,
                code: option.description ?? null,
              } as AdminSupplier)
            : null,
        );
      }}
    />
  );
}
