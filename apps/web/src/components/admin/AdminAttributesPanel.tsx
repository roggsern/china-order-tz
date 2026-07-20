"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminCatalogAttribute,
  createAdminCatalogAttributeOption,
  deleteAdminCatalogAttribute,
  deleteAdminCatalogAttributeOption,
  fetchAdminCatalogAttributes,
  fetchAdminCatalogProductTypes,
  restoreAdminCatalogAttribute,
  syncAdminCatalogProductTypeAttributes,
  updateAdminCatalogAttribute,
  updateAdminCatalogAttributeOption,
  type AdminCatalogAttribute,
  type AdminCatalogProductType,
  type CatalogAttributeType,
} from "@/lib/api/admin-catalog";

type AttributeFormState = {
  id?: string;
  name: string;
  slug: string;
  type: CatalogAttributeType;
  unit: string;
  isFilterable: boolean;
  isRequired: boolean;
  sortOrder: number;
  isActive: boolean;
  optionDraft: string;
};

const emptyForm = (): AttributeFormState => ({
  name: "",
  slug: "",
  type: "select",
  unit: "",
  isFilterable: true,
  isRequired: false,
  sortOrder: 0,
  isActive: true,
  optionDraft: "",
});

const ATTRIBUTE_TYPES: CatalogAttributeType[] = [
  "text",
  "number",
  "boolean",
  "select",
  "multiselect",
];

export function AdminAttributesPanel() {
  const [attributes, setAttributes] = useState<AdminCatalogAttribute[]>([]);
  const [productTypes, setProductTypes] = useState<AdminCatalogProductType[]>([]);
  const [trashed, setTrashed] = useState<AdminCatalogAttribute[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState<"all" | CatalogAttributeType>("all");
  const [showTrashed, setShowTrashed] = useState(false);
  const [form, setForm] = useState<AttributeFormState | null>(null);
  const [saving, setSaving] = useState(false);
  const [mappingTypeId, setMappingTypeId] = useState("");
  const [selectedAttributeIds, setSelectedAttributeIds] = useState<string[]>([]);
  const [mappingSaving, setMappingSaving] = useState(false);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const [nextAttributes, deleted, nextTypes] = await Promise.all([
        fetchAdminCatalogAttributes(),
        fetchAdminCatalogAttributes({ trashed: true }),
        fetchAdminCatalogProductTypes(),
      ]);
      setAttributes(nextAttributes);
      setTrashed(deleted);
      setProductTypes(nextTypes);
    } catch (err) {
      setAttributes([]);
      setTrashed([]);
      setProductTypes([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load attributes from the API.",
      );
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const filteredAttributes = useMemo(() => {
    const q = search.trim().toLowerCase();
    return attributes.filter((attribute) => {
      if (typeFilter !== "all" && attribute.type !== typeFilter) return false;
      if (!q) return true;
      return (
        attribute.name.toLowerCase().includes(q) ||
        attribute.slug.toLowerCase().includes(q)
      );
    });
  }, [attributes, search, typeFilter]);

  const selectedAttribute = useMemo(
    () => attributes.find((attribute) => attribute.id === form?.id) ?? null,
    [attributes, form?.id],
  );

  const needsOptions = form?.type === "select" || form?.type === "multiselect";

  const openCreate = () => {
    setActionError(null);
    setForm(emptyForm());
  };

  const openEdit = (attribute: AdminCatalogAttribute) => {
    setActionError(null);
    setForm({
      id: attribute.id,
      name: attribute.name,
      slug: attribute.slug,
      type: attribute.type,
      unit: attribute.unit ?? "",
      isFilterable: attribute.isFilterable,
      isRequired: attribute.isRequired,
      sortOrder: attribute.sortOrder,
      isActive: attribute.isActive,
      optionDraft: "",
    });
  };

  const handleDelete = async (attribute: AdminCatalogAttribute) => {
    if (!window.confirm(`Delete attribute “${attribute.name}”?`)) return;
    setActionError(null);
    try {
      await deleteAdminCatalogAttribute(attribute.id);
      await reload();
      if (form?.id === attribute.id) setForm(null);
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to delete attribute.",
      );
    }
  };

  const handleRestore = async (attribute: AdminCatalogAttribute) => {
    setActionError(null);
    try {
      await restoreAdminCatalogAttribute(attribute.id);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to restore attribute.",
      );
    }
  };

  const handleSave = async () => {
    if (!form || !form.name.trim()) {
      setActionError("Attribute name is required.");
      return;
    }

    setSaving(true);
    setActionError(null);

    const payload = {
      name: form.name.trim(),
      slug: form.slug.trim() || null,
      type: form.type,
      unit: form.unit.trim() || null,
      is_filterable: form.isFilterable,
      is_required: form.isRequired,
      sort_order: form.sortOrder,
      is_active: form.isActive,
    };

    try {
      if (form.id) {
        await updateAdminCatalogAttribute(form.id, payload);
      } else {
        await createAdminCatalogAttribute(payload);
      }
      setForm(null);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to save attribute.",
      );
    } finally {
      setSaving(false);
    }
  };

  const handleAddOption = async () => {
    if (!form?.id || !form.optionDraft.trim()) {
      setActionError("Save the attribute first, then add an option value.");
      return;
    }
    setActionError(null);
    try {
      await createAdminCatalogAttributeOption(form.id, {
        value: form.optionDraft.trim(),
        sort_order: (selectedAttribute?.options.length ?? 0) + 1,
      });
      setForm({ ...form, optionDraft: "" });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to add option.",
      );
    }
  };

  const handleRenameOption = async (optionId: string, value: string) => {
    const next = window.prompt("Option value", value);
    if (!next || !next.trim()) return;
    setActionError(null);
    try {
      await updateAdminCatalogAttributeOption(optionId, { value: next.trim() });
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to update option.",
      );
    }
  };

  const handleDeleteOption = async (optionId: string) => {
    if (!window.confirm("Delete this option?")) return;
    setActionError(null);
    try {
      await deleteAdminCatalogAttributeOption(optionId);
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to delete option.",
      );
    }
  };

  useEffect(() => {
    if (!mappingTypeId) {
      setSelectedAttributeIds([]);
      return;
    }
    const type = productTypes.find((item) => item.id === mappingTypeId);
    if (!type) return;
    // Load assigned attributes from attribute.catalogProductTypeIds
    const assigned = attributes
      .filter((attribute) => attribute.catalogProductTypeIds.includes(mappingTypeId))
      .map((attribute) => attribute.id);
    setSelectedAttributeIds(assigned);
  }, [mappingTypeId, attributes, productTypes]);

  // Show endpoint doesn't always include catalog_product_types on list.
  // Re-fetch show data when selecting a type by syncing from attributes that have mappings.
  // List resource may not include catalog_product_types - need to load from show or sync endpoint.
  // For mapping UI we toggle checkboxes and PUT sync.

  const handleSaveMapping = async () => {
    if (!mappingTypeId) {
      setActionError("Select a product type to map attributes.");
      return;
    }
    setMappingSaving(true);
    setActionError(null);
    try {
      await syncAdminCatalogProductTypeAttributes(
        mappingTypeId,
        selectedAttributeIds.map((id, index) => ({
          catalog_attribute_id: id,
          is_required: false,
          sort_order: index + 1,
        })),
      );
      await reload();
    } catch (err) {
      setActionError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save product type attributes.",
      );
    } finally {
      setMappingSaving(false);
    }
  };

  const toggleMappedAttribute = (attributeId: string) => {
    setSelectedAttributeIds((prev) =>
      prev.includes(attributeId)
        ? prev.filter((id) => id !== attributeId)
        : [...prev, attributeId],
    );
  };

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-zinc-900">Attributes</h1>
          <p className="mt-1 text-xs text-zinc-500">
            Reusable catalog attributes, options, and product-type mappings for filters.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            className="admin-btn-secondary"
            onClick={() => setShowTrashed((value) => !value)}
          >
            {showTrashed ? "Hide trash" : `Trash (${trashed.length})`}
          </button>
          <button type="button" className="admin-btn-primary" onClick={openCreate}>
            Add attribute
          </button>
        </div>
      </div>

      {actionError ? (
        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {actionError}
        </div>
      ) : null}

      {form ? (
        <div className="admin-card mb-4 p-5">
          <h2 className="text-sm font-semibold text-zinc-900">
            {form.id ? "Edit attribute" : "New attribute"}
          </h2>
          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
              <label className="admin-label" htmlFor="attr-name">
                Name *
              </label>
              <input
                id="attr-name"
                className="admin-input mt-1.5"
                value={form.name}
                onChange={(event) => setForm({ ...form, name: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="attr-type">
                Type *
              </label>
              <select
                id="attr-type"
                className="admin-input mt-1.5"
                value={form.type}
                onChange={(event) =>
                  setForm({ ...form, type: event.target.value as CatalogAttributeType })
                }
              >
                {ATTRIBUTE_TYPES.map((type) => (
                  <option key={type} value={type}>
                    {type}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="admin-label" htmlFor="attr-slug">
                Slug
              </label>
              <input
                id="attr-slug"
                className="admin-input mt-1.5"
                value={form.slug}
                onChange={(event) => setForm({ ...form, slug: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="attr-unit">
                Unit
              </label>
              <input
                id="attr-unit"
                className="admin-input mt-1.5"
                placeholder="GB, W, inch…"
                value={form.unit}
                onChange={(event) => setForm({ ...form, unit: event.target.value })}
              />
            </div>
            <div>
              <label className="admin-label" htmlFor="attr-sort">
                Sort order
              </label>
              <input
                id="attr-sort"
                type="number"
                min={0}
                className="admin-input mt-1.5"
                value={form.sortOrder}
                onChange={(event) =>
                  setForm({
                    ...form,
                    sortOrder: Number.parseInt(event.target.value, 10) || 0,
                  })
                }
              />
            </div>
            <div className="flex flex-col gap-2 justify-end">
              <label className="flex items-center gap-2 text-sm text-zinc-700">
                <input
                  type="checkbox"
                  checked={form.isFilterable}
                  onChange={(event) =>
                    setForm({ ...form, isFilterable: event.target.checked })
                  }
                />
                Filterable
              </label>
              <label className="flex items-center gap-2 text-sm text-zinc-700">
                <input
                  type="checkbox"
                  checked={form.isRequired}
                  onChange={(event) => setForm({ ...form, isRequired: event.target.checked })}
                />
                Required
              </label>
              <label className="flex items-center gap-2 text-sm text-zinc-700">
                <input
                  type="checkbox"
                  checked={form.isActive}
                  onChange={(event) => setForm({ ...form, isActive: event.target.checked })}
                />
                Active
              </label>
            </div>
          </div>

          {form.id && needsOptions ? (
            <div className="mt-5">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                Options
              </h3>
              <div className="mt-2 flex gap-2">
                <input
                  className="admin-input flex-1"
                  placeholder="Add option value"
                  value={form.optionDraft}
                  onChange={(event) => setForm({ ...form, optionDraft: event.target.value })}
                />
                <button
                  type="button"
                  className="admin-btn-secondary"
                  onClick={() => void handleAddOption()}
                >
                  Add option
                </button>
              </div>
              <ul className="mt-3 divide-y divide-zinc-100 rounded-lg border border-zinc-200">
                {(selectedAttribute?.options ?? []).map((option) => (
                  <li
                    key={option.id}
                    className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                  >
                    <span>{option.value}</span>
                    <div className="flex gap-1">
                      <button
                        type="button"
                        className="rounded px-2 py-1 text-[11px] text-zinc-600 hover:bg-zinc-100"
                        onClick={() => void handleRenameOption(option.id, option.value)}
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        className="rounded px-2 py-1 text-[11px] text-red-600 hover:bg-red-50"
                        onClick={() => void handleDeleteOption(option.id)}
                      >
                        Delete
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <div className="mt-4 flex gap-2">
            <button
              type="button"
              className="admin-btn-primary"
              disabled={saving}
              onClick={() => void handleSave()}
            >
              {saving ? "Saving…" : "Save"}
            </button>
            <button
              type="button"
              className="admin-btn-secondary"
              disabled={saving}
              onClick={() => setForm(null)}
            >
              Cancel
            </button>
          </div>
        </div>
      ) : null}

      <div className="admin-card mb-4 overflow-hidden">
        <div className="border-b border-zinc-200 px-5 py-3">
          <h2 className="text-sm font-semibold text-zinc-900">Product type mapping</h2>
          <p className="mt-1 text-xs text-zinc-500">
            Assign attributes to a catalog product type (e.g. PA System, Smartphone).
          </p>
        </div>
        <div className="space-y-3 p-5">
          <select
            className="admin-input"
            value={mappingTypeId}
            onChange={(event) => setMappingTypeId(event.target.value)}
          >
            <option value="">Select product type</option>
            {productTypes.map((type) => (
              <option key={type.id} value={type.id}>
                {type.name}
              </option>
            ))}
          </select>
          {mappingTypeId ? (
            <>
              <div className="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-3">
                {attributes.map((attribute) => (
                  <label
                    key={attribute.id}
                    className="flex items-center gap-2 rounded px-2 py-1.5 text-sm text-zinc-700 hover:bg-zinc-50"
                  >
                    <input
                      type="checkbox"
                      checked={selectedAttributeIds.includes(attribute.id)}
                      onChange={() => toggleMappedAttribute(attribute.id)}
                    />
                    <span>
                      {attribute.name}
                      <span className="ml-2 text-[11px] text-zinc-400">
                        {attribute.type}
                        {attribute.isFilterable ? " · filterable" : ""}
                      </span>
                    </span>
                  </label>
                ))}
              </div>
              <button
                type="button"
                className="admin-btn-primary"
                disabled={mappingSaving}
                onClick={() => void handleSaveMapping()}
              >
                {mappingSaving ? "Saving mapping…" : "Save mapping"}
              </button>
            </>
          ) : null}
        </div>
      </div>

      <div className="admin-card overflow-hidden">
        <div className="flex flex-wrap gap-3 border-b border-zinc-200 px-4 py-3">
          <input
            type="search"
            className="admin-input min-w-[200px] flex-1"
            placeholder="Search attributes…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <select
            className="admin-input w-auto"
            value={typeFilter}
            onChange={(event) =>
              setTypeFilter(event.target.value as "all" | CatalogAttributeType)
            }
          >
            <option value="all">All types</option>
            {ATTRIBUTE_TYPES.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
        </div>

        {isLoading ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">Loading attributes…</div>
        ) : error ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-medium text-zinc-700">Unable to load attributes</p>
            <p className="mt-1 text-xs text-zinc-500">{error}</p>
          </div>
        ) : filteredAttributes.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-zinc-500">
            No attributes configured.
          </div>
        ) : (
          <ul className="divide-y divide-zinc-100">
            {filteredAttributes.map((attribute) => (
              <li key={attribute.id} className="flex flex-wrap items-start gap-3 px-5 py-3">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-zinc-900">
                    {attribute.name}
                    {!attribute.isActive ? (
                      <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-500">
                        Inactive
                      </span>
                    ) : null}
                  </p>
                  <p className="text-xs text-zinc-500">
                    {attribute.slug} · {attribute.type}
                    {attribute.unit ? ` · ${attribute.unit}` : ""}
                    {attribute.isFilterable ? " · filterable" : ""}
                    {attribute.options.length > 0
                      ? ` · ${attribute.options.length} options`
                      : ""}
                  </p>
                </div>
                <div className="flex shrink-0 gap-1">
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                    onClick={() => openEdit(attribute)}
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                    onClick={() => void handleDelete(attribute)}
                  >
                    Delete
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      {showTrashed ? (
        <div className="admin-card mt-4 overflow-hidden">
          <div className="border-b border-zinc-200 px-5 py-3">
            <h2 className="text-sm font-semibold text-zinc-900">Deleted attributes</h2>
          </div>
          {trashed.length === 0 ? (
            <div className="px-5 py-8 text-center text-sm text-zinc-500">Trash is empty.</div>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {trashed.map((attribute) => (
                <li key={attribute.id} className="flex items-center gap-3 px-5 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{attribute.name}</p>
                    <p className="text-xs text-zinc-500">{attribute.slug}</p>
                  </div>
                  <button
                    type="button"
                    className="rounded px-2 py-1 text-[11px] font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                    onClick={() => void handleRestore(attribute)}
                  >
                    Restore
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      ) : null}
    </div>
  );
}
