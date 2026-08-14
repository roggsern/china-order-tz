"use client";

import { useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  fetchAdminDepartments,
  fetchTaxonomyImportSource,
  importTaxonomyToStore,
  type AdminDepartment,
  type TaxonomyImportSourceCategory,
} from "@/lib/api/admin-catalog";
import {
  buildTaxonomyImportPayload,
  buildTaxonomyImportSummary,
  taxonomyNodeProductTypeLabel,
  toggleTaxonomyImportSelection,
  type TaxonomyImportSourceNode,
} from "@/lib/admin/taxonomy-import";

type Props = {
  storeId: string;
  storeName: string;
  open: boolean;
  onClose: () => void;
  onImported: () => void;
};

function toNodes(categories: TaxonomyImportSourceCategory[]): TaxonomyImportSourceNode[] {
  return categories.map((category) => ({
    id: category.id,
    name: category.name,
    slug: category.slug,
    parentId: category.parentId,
    sortOrder: category.sortOrder,
    isActive: category.isActive,
    importable: category.importable,
    productTypes: category.productTypes.map((type) => ({
      id: type.id,
      name: type.name,
      attributesCount: type.attributesCount,
      hasAttributeMappings: type.hasAttributeMappings,
    })),
  }));
}

export function AdminImportTaxonomyModal({
  storeId,
  storeName,
  open,
  onClose,
  onImported,
}: Props) {
  const [departments, setDepartments] = useState<AdminDepartment[]>([]);
  const [departmentId, setDepartmentId] = useState("");
  const [categories, setCategories] = useState<TaxonomyImportSourceCategory[]>([]);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [includeProductTypes, setIncludeProductTypes] = useState(true);
  const [includeAttributeMappings, setIncludeAttributeMappings] = useState(true);
  const [loadingMeta, setLoadingMeta] = useState(false);
  const [loadingSource, setLoadingSource] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setError(null);
    setSuccess(null);
    setSelectedIds([]);
    setIncludeProductTypes(true);
    setIncludeAttributeMappings(true);
    setLoadingMeta(true);
    void fetchAdminDepartments()
      .then((rows) => {
        const active = rows.filter((row) => row.isActive !== false);
        setDepartments(active);
        setDepartmentId((current) => current || active[0]?.id || "");
      })
      .catch((err: unknown) => {
        setDepartments([]);
        setError(
          err instanceof AdminCatalogApiError
            ? err.message
            : "Unable to load departments.",
        );
      })
      .finally(() => setLoadingMeta(false));
  }, [open]);

  useEffect(() => {
    if (!open || !departmentId || !storeId) {
      setCategories([]);
      return;
    }
    setLoadingSource(true);
    setError(null);
    setSelectedIds([]);
    void fetchTaxonomyImportSource({ storeId, departmentId })
      .then((source) => setCategories(source.categories))
      .catch((err: unknown) => {
        setCategories([]);
        setError(
          err instanceof AdminCatalogApiError
            ? err.message
            : "Unable to load China taxonomy for import.",
        );
      })
      .finally(() => setLoadingSource(false));
  }, [open, departmentId, storeId]);

  const nodes = useMemo(() => toNodes(categories), [categories]);

  const roots = useMemo(
    () =>
      nodes
        .filter((node) => !node.parentId)
        .sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name)),
    [nodes],
  );

  const childrenByParent = useMemo(() => {
    const map = new Map<string, TaxonomyImportSourceNode[]>();
    for (const node of nodes) {
      if (!node.parentId) continue;
      const list = map.get(node.parentId) ?? [];
      list.push(node);
      map.set(node.parentId, list);
    }
    for (const list of map.values()) {
      list.sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name));
    }
    return map;
  }, [nodes]);

  const summary = useMemo(
    () =>
      buildTaxonomyImportSummary({
        selectedIds,
        nodes,
        includeProductTypes,
        includeAttributeMappings,
      }),
    [selectedIds, nodes, includeProductTypes, includeAttributeMappings],
  );

  if (!open) {
    return null;
  }

  const handleSubmit = async () => {
    setError(null);
    setSuccess(null);
    if (!departmentId) {
      setError("Select a source department.");
      return;
    }
    if (selectedIds.length === 0) {
      setError("Select at least one category.");
      return;
    }
    setSubmitting(true);
    try {
      const payload = buildTaxonomyImportPayload({
        departmentId,
        selectedIds,
        includeProductTypes,
        includeAttributeMappings,
      });
      const result = await importTaxonomyToStore({
        storeId,
        departmentId: payload.department_id,
        categoryIds: payload.category_ids,
        includeProductTypes: payload.include_product_types,
        includeAttributeMappings: payload.include_attribute_mappings,
      });
      setSuccess(
        `Imported into ${storeName}: ${result.categoriesCreated} categories created, ${result.categoriesReused} reused` +
          (payload.include_product_types
            ? `; ${result.productTypesCreated} product types created, ${result.productTypesReused} reused`
            : "") +
          (payload.include_attribute_mappings
            ? `; ${result.attributeMappingsSynced} attribute mappings synced`
            : "") +
          (result.productTypesSkippedNoSource > 0
            ? `; ${result.productTypesSkippedNoSource} leaf categor${result.productTypesSkippedNoSource === 1 ? "y" : "ies"} had no source Product Type (categories still imported)`
            : "") +
          ".",
      );
      onImported();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to import taxonomy.",
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8">
      <div
        className="w-full max-w-2xl rounded-xl border border-zinc-200 bg-white shadow-xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="taxonomy-import-title"
      >
        <div className="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4">
          <div>
            <h2 id="taxonomy-import-title" className="text-base font-semibold text-zinc-900">
              Add from existing taxonomy
            </h2>
            <p className="mt-1 text-xs text-zinc-500">
              Copy selected China catalog nodes into{" "}
              <span className="font-medium text-zinc-700">{storeName}</span> as
              store-owned TZ categories. China source rows are never modified.
            </p>
          </div>
          <button
            type="button"
            className="rounded-md px-2 py-1 text-sm text-zinc-500 hover:bg-zinc-100"
            onClick={onClose}
            disabled={submitting}
          >
            Close
          </button>
        </div>

        <div className="space-y-4 px-5 py-4">
          {error ? (
            <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
              {error}
            </div>
          ) : null}
          {success ? (
            <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
              {success}
            </div>
          ) : null}

          <div>
            <label className="admin-label" htmlFor="taxonomy-import-department">
              Source department
            </label>
            <select
              id="taxonomy-import-department"
              className="admin-input mt-1.5"
              value={departmentId}
              disabled={loadingMeta || submitting}
              onChange={(event) => setDepartmentId(event.target.value)}
            >
              <option value="">Select department…</option>
              {departments.map((department) => (
                <option key={department.id} value={department.id}>
                  {department.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <p className="admin-label">Available taxonomy</p>
            <div className="mt-1.5 max-h-72 overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50/60 p-3">
              {loadingSource ? (
                <p className="text-sm text-zinc-500">Loading China taxonomy…</p>
              ) : roots.length === 0 ? (
                <p className="text-sm text-zinc-500">
                  No China taxonomy categories in this department.
                </p>
              ) : (
                <ul className="space-y-2">
                  {roots.map((root) => {
                    const children = childrenByParent.get(root.id) ?? [];
                    return (
                      <li key={root.id}>
                        <label className="flex items-start gap-2 text-sm text-zinc-800">
                          <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={selectedIds.includes(root.id)}
                            disabled={submitting || !root.importable}
                            onChange={(event) =>
                              setSelectedIds(
                                toggleTaxonomyImportSelection({
                                  selectedIds,
                                  nodes,
                                  nodeId: root.id,
                                  checked: event.target.checked,
                                }),
                              )
                            }
                          />
                          <span>
                            <span className="font-medium">{root.name}</span>
                            {!root.isActive ? (
                              <span className="ml-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-400">
                                parent
                              </span>
                            ) : null}
                            <span className="ml-1 text-xs text-zinc-500">
                              ({taxonomyNodeProductTypeLabel(root)})
                            </span>
                          </span>
                        </label>
                        {children.length > 0 ? (
                          <ul className="mt-2 space-y-1.5 border-l border-zinc-200 pl-5">
                            {children.map((child) => (
                              <li key={child.id}>
                                <label className="flex items-start gap-2 text-sm text-zinc-700">
                                  <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={selectedIds.includes(child.id)}
                                    disabled={submitting || !child.importable}
                                    onChange={(event) =>
                                      setSelectedIds(
                                        toggleTaxonomyImportSelection({
                                          selectedIds,
                                          nodes,
                                          nodeId: child.id,
                                          checked: event.target.checked,
                                        }),
                                      )
                                    }
                                  />
                                  <span>
                                    {child.name}
                                    <span className="ml-1 text-xs text-zinc-500">
                                      ({taxonomyNodeProductTypeLabel(child)})
                                    </span>
                                  </span>
                                </label>
                              </li>
                            ))}
                          </ul>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
            <p className="mt-1 text-xs text-zinc-500">
              Selecting a child also selects its required parent. Taxonomy appears even when China
              has no products yet. Product Types are optional and listed separately.
            </p>
          </div>

          <div className="space-y-2 rounded-lg border border-zinc-200 px-3 py-3">
            <label className="flex items-center gap-2 text-sm text-zinc-800">
              <input
                type="checkbox"
                checked={includeProductTypes}
                disabled={submitting}
                onChange={(event) => {
                  const next = event.target.checked;
                  setIncludeProductTypes(next);
                  if (!next) setIncludeAttributeMappings(false);
                }}
              />
              Include compatible Product Types
            </label>
            <label className="flex items-center gap-2 text-sm text-zinc-800">
              <input
                type="checkbox"
                checked={includeAttributeMappings}
                disabled={submitting || !includeProductTypes}
                onChange={(event) => setIncludeAttributeMappings(event.target.checked)}
              />
              Include attribute mappings (reuse global attribute definitions)
            </label>
          </div>

          {summary.categoryCount > 0 ? (
            <div className="rounded-lg border border-amber-100 bg-amber-50/70 px-3 py-2 text-xs text-zinc-700">
              <p className="font-medium text-zinc-900">Summary</p>
              <p className="mt-1">
                {summary.categoryCount} categor
                {summary.categoryCount === 1 ? "y" : "ies"}
                {includeProductTypes
                  ? ` · ${summary.productTypeCount} product type${summary.productTypeCount === 1 ? "" : "s"}`
                  : ""}
                {includeProductTypes && includeAttributeMappings
                  ? ` · ${summary.attributeMappedTypeCount} with attribute mappings`
                  : ""}
                {includeProductTypes && summary.leavesWithoutProductTypes > 0
                  ? ` · ${summary.leavesWithoutProductTypes} with no source Product Type`
                  : ""}
              </p>
              <p className="mt-1 text-zinc-600">{summary.labels.join(", ")}</p>
            </div>
          ) : null}
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t border-zinc-100 px-5 py-4">
          <button
            type="button"
            className="admin-btn-secondary"
            onClick={onClose}
            disabled={submitting}
          >
            Cancel
          </button>
          <button
            type="button"
            className="admin-btn-primary"
            onClick={() => void handleSubmit()}
            disabled={submitting || loadingSource || selectedIds.length === 0}
          >
            {submitting ? "Importing…" : `Add to ${storeName}`}
          </button>
        </div>
      </div>
    </div>
  );
}
