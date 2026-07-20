"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminCatalogApiError,
  fetchAdminProductSpecifications,
  syncAdminProductSpecifications,
  type AdminProductSpecAttribute,
  type AdminProductSpecWriteRow,
} from "@/lib/api/admin-catalog";

type ProductSpecificationsManagerProps = {
  productId: string;
};

type DraftValue = {
  valueText: string;
  valueNumber: string;
  valueBoolean: boolean | null;
  optionId: string;
  optionIds: string[];
};

function toDraft(item: AdminProductSpecAttribute): DraftValue {
  return {
    valueText: item.value.valueText ?? "",
    valueNumber:
      item.value.valueNumber === null || item.value.valueNumber === undefined
        ? ""
        : String(item.value.valueNumber),
    valueBoolean: item.value.valueBoolean,
    optionId: item.value.optionId ?? "",
    optionIds: [...item.value.optionIds],
  };
}

export function ProductSpecificationsManager({
  productId,
}: ProductSpecificationsManagerProps) {
  const [schema, setSchema] = useState<AdminProductSpecAttribute[]>([]);
  const [drafts, setDrafts] = useState<Record<string, DraftValue>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const rows = await fetchAdminProductSpecifications(productId);
      setSchema(rows);
      const next: Record<string, DraftValue> = {};
      for (const row of rows) {
        next[row.catalogAttributeId] = toDraft(row);
      }
      setDrafts(next);
    } catch (err) {
      setSchema([]);
      setDrafts({});
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load specifications.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const updateDraft = (attributeId: string, patch: Partial<DraftValue>) => {
    setDrafts((current) => ({
      ...current,
      [attributeId]: {
        ...current[attributeId],
        ...patch,
      },
    }));
  };

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    setSuccess(null);

    const attributes: AdminProductSpecWriteRow[] = schema.map((item) => {
      const draft = drafts[item.catalogAttributeId] ?? toDraft(item);
      const row: AdminProductSpecWriteRow = {
        catalog_attribute_id: item.catalogAttributeId,
      };

      if (item.type === "text") {
        row.value_text = draft.valueText.trim() || null;
      } else if (item.type === "number") {
        row.value_number =
          draft.valueNumber.trim() === "" ? null : Number(draft.valueNumber);
      } else if (item.type === "boolean") {
        row.value_boolean = draft.valueBoolean;
      } else if (item.type === "select") {
        row.option_id = draft.optionId || null;
      } else if (item.type === "multiselect") {
        row.option_ids = draft.optionIds;
      }

      return row;
    });

    try {
      const rows = await syncAdminProductSpecifications(productId, attributes);
      setSchema(rows);
      const next: Record<string, DraftValue> = {};
      for (const row of rows) {
        next[row.catalogAttributeId] = toDraft(row);
      }
      setDrafts(next);
      setSuccess("Specifications saved.");
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save specifications.",
      );
    } finally {
      setSaving(false);
    }
  };

  if (isLoading) {
    return <p className="text-sm text-zinc-500">Loading specifications…</p>;
  }

  if (schema.length === 0 && !error) {
    return (
      <p className="text-sm text-zinc-500">
        No attributes assigned to this product type. Assign attributes under Catalog →
        Attributes first.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {success}
        </div>
      ) : null}

      <p className="text-xs text-zinc-500">
        Values are loaded from the product type’s assigned catalog attributes. Required fields
        are marked with *.
      </p>

      <div className="grid gap-4 sm:grid-cols-2">
        {schema.map((item) => {
          const draft = drafts[item.catalogAttributeId] ?? toDraft(item);
          const label = `${item.name}${item.unit ? ` (${item.unit})` : ""}${
            item.isRequired ? " *" : ""
          }`;

          return (
            <div key={item.catalogAttributeId} className="rounded-lg border border-zinc-200 p-3">
              <label className="admin-label" htmlFor={`spec-${item.catalogAttributeId}`}>
                {label}
              </label>
              <p className="mt-0.5 text-[11px] uppercase tracking-wide text-zinc-400">
                {item.type}
              </p>

              {item.type === "text" ? (
                <input
                  id={`spec-${item.catalogAttributeId}`}
                  className="admin-input mt-2"
                  value={draft.valueText}
                  onChange={(event) =>
                    updateDraft(item.catalogAttributeId, { valueText: event.target.value })
                  }
                />
              ) : null}

              {item.type === "number" ? (
                <input
                  id={`spec-${item.catalogAttributeId}`}
                  type="number"
                  step="any"
                  className="admin-input mt-2"
                  value={draft.valueNumber}
                  onChange={(event) =>
                    updateDraft(item.catalogAttributeId, { valueNumber: event.target.value })
                  }
                />
              ) : null}

              {item.type === "boolean" ? (
                <select
                  id={`spec-${item.catalogAttributeId}`}
                  className="admin-input mt-2"
                  value={
                    draft.valueBoolean === null ? "" : draft.valueBoolean ? "true" : "false"
                  }
                  onChange={(event) => {
                    const raw = event.target.value;
                    updateDraft(item.catalogAttributeId, {
                      valueBoolean: raw === "" ? null : raw === "true",
                    });
                  }}
                >
                  <option value="">Select…</option>
                  <option value="true">Yes</option>
                  <option value="false">No</option>
                </select>
              ) : null}

              {item.type === "select" ? (
                <select
                  id={`spec-${item.catalogAttributeId}`}
                  className="admin-input mt-2"
                  value={draft.optionId}
                  onChange={(event) =>
                    updateDraft(item.catalogAttributeId, { optionId: event.target.value })
                  }
                >
                  <option value="">Select…</option>
                  {item.options.map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.value}
                    </option>
                  ))}
                </select>
              ) : null}

              {item.type === "multiselect" ? (
                <div className="mt-2 max-h-40 space-y-1 overflow-y-auto rounded border border-zinc-200 p-2">
                  {item.options.map((option) => {
                    const checked = draft.optionIds.includes(option.id);
                    return (
                      <label
                        key={option.id}
                        className="flex items-center gap-2 text-sm text-zinc-700"
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => {
                            const optionIds = checked
                              ? draft.optionIds.filter((id) => id !== option.id)
                              : [...draft.optionIds, option.id];
                            updateDraft(item.catalogAttributeId, { optionIds });
                          }}
                        />
                        {option.value}
                      </label>
                    );
                  })}
                </div>
              ) : null}
            </div>
          );
        })}
      </div>

      <div className="flex gap-2">
        <button
          type="button"
          className="admin-btn-primary"
          disabled={saving}
          onClick={() => void handleSave()}
        >
          {saving ? "Saving…" : "Save specifications"}
        </button>
        <button
          type="button"
          className="admin-btn-secondary"
          disabled={saving}
          onClick={() => void reload()}
        >
          Reload
        </button>
      </div>
    </div>
  );
}
