"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminAsyncSearchSelect,
  type AdminAsyncOption,
} from "@/components/admin/AdminAsyncSearchSelect";
import { AdminInlineCreateBrandModal } from "@/components/admin/AdminInlineCreateBrandModal";
import {
  fetchAdminBrandsPage,
  type AdminBrand,
} from "@/lib/api/admin-catalog";

type AdminBrandAsyncSelectProps = {
  id: string;
  value: string;
  selectedLabel?: string;
  categoryId?: string | null;
  disabled?: boolean;
  canCreate?: boolean;
  onChange: (brandId: string, brand: AdminBrand | null) => void;
};

export function AdminBrandAsyncSelect({
  id,
  value,
  selectedLabel,
  categoryId = null,
  disabled = false,
  canCreate = false,
  onChange,
}: AdminBrandAsyncSelectProps) {
  const [showAllBrands, setShowAllBrands] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [labelOverride, setLabelOverride] = useState<string | null>(null);

  const leafCategoryId = categoryId?.trim() || undefined;
  const effectiveLabel = labelOverride ?? selectedLabel;

  useEffect(() => {
    setShowAllBrands(false);
  }, [leafCategoryId]);

  const loadOptions = useCallback(
    async (query: string, page: number) => {
      const result = await fetchAdminBrandsPage({
        search: query || undefined,
        categoryId: leafCategoryId,
        allBrands: showAllBrands || !leafCategoryId,
        isActive: true,
        page,
        perPage: 20,
      });

      return {
        items: result.items.map(
          (brand): AdminAsyncOption => ({
            id: brand.id,
            label: brand.name,
            description: brand.country || undefined,
          }),
        ),
        hasMore: result.page < result.lastPage,
      };
    },
    [leafCategoryId, showAllBrands],
  );

  const reloadKey = useMemo(
    () => `${leafCategoryId ?? ""}:${showAllBrands ? "all" : "scoped"}`,
    [leafCategoryId, showAllBrands],
  );

  return (
    <div className="space-y-2">
      <AdminAsyncSearchSelect
        id={id}
        value={value}
        selectedLabel={effectiveLabel}
        disabled={disabled}
        placeholder="Search brands by name…"
        emptyMessage="No brands match your search."
        reloadKey={reloadKey}
        loadOptions={loadOptions}
        onChange={(nextId, option) => {
          setLabelOverride(option?.label ?? null);
          onChange(nextId, option ? ({ id: option.id, name: option.label } as AdminBrand) : null);
        }}
        footer={
          <div className="flex flex-wrap items-center justify-between gap-2">
            {leafCategoryId ? (
              <label className="flex items-center gap-2 text-xs text-zinc-600">
                <input
                  type="checkbox"
                  checked={showAllBrands}
                  onChange={(event) => setShowAllBrands(event.target.checked)}
                />
                Show all brands
              </label>
            ) : (
              <span className="text-xs text-zinc-500">Type to search all brands</span>
            )}
            {canCreate ? (
              <button
                type="button"
                className="text-xs font-bold text-[#8b6914] hover:underline"
                onClick={() => setCreateOpen(true)}
              >
                + Create new brand
              </button>
            ) : null}
          </div>
        }
      />

      <AdminInlineCreateBrandModal
        open={createOpen}
        categoryIds={leafCategoryId ? [leafCategoryId] : []}
        onClose={() => setCreateOpen(false)}
        onCreated={(brand) => {
          setCreateOpen(false);
          setLabelOverride(brand.name);
          onChange(brand.id, brand);
        }}
      />
    </div>
  );
}
