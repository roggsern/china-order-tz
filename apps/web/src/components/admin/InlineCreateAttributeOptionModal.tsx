"use client";

import { useState } from "react";
import {
  findDuplicateAttributeOption,
  nextAttributeOptionSortOrder,
} from "@/lib/admin/inline-attribute-option";
import {
  AdminCatalogApiError,
  createAdminCatalogAttributeOption,
  type AdminCatalogAttributeOption,
} from "@/lib/api/admin-catalog";

type InlineCreateAttributeOptionModalProps = {
  open: boolean;
  attributeId: string;
  attributeName: string;
  existingOptions: readonly { value: string; sortOrder?: number }[];
  onClose: () => void;
  onCreated: (option: AdminCatalogAttributeOption) => void;
};

export function InlineCreateAttributeOptionModal({
  open,
  attributeId,
  attributeName,
  existingOptions,
  onClose,
  onCreated,
}: InlineCreateAttributeOptionModalProps) {
  const [value, setValue] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!open) {
    return null;
  }

  const reset = () => {
    setValue("");
    setError(null);
  };

  const handleClose = () => {
    if (saving) return;
    reset();
    onClose();
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    const trimmed = value.trim();
    if (!trimmed) {
      setError("Option value is required.");
      return;
    }

    const duplicate = findDuplicateAttributeOption(existingOptions, trimmed);
    if (duplicate) {
      setError(`“${duplicate.value}” already exists for ${attributeName}.`);
      return;
    }

    setSaving(true);
    setError(null);
    try {
      const option = await createAdminCatalogAttributeOption(attributeId, {
        value: trimmed,
        sort_order: nextAttributeOptionSortOrder(existingOptions),
      });
      reset();
      onCreated(option);
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to create attribute option.",
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 p-4">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="inline-attr-option-title"
        className="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-5 shadow-xl"
      >
        <h2 id="inline-attr-option-title" className="text-lg font-bold text-zinc-900">
          Add {attributeName} option
        </h2>
        <p className="mt-1 text-sm text-zinc-500">
          Creates a catalog option for this attribute. It will appear and be selected
          immediately — you stay in the product wizard.
        </p>

        <form className="mt-4 space-y-3" onSubmit={(event) => void handleSubmit(event)}>
          <div>
            <label className="admin-label" htmlFor="inline-attr-option-value">
              Value *
            </label>
            <input
              id="inline-attr-option-value"
              className="admin-input mt-1.5"
              value={value}
              onChange={(event) => setValue(event.target.value)}
              placeholder={`e.g. new ${attributeName.toLowerCase()} value`}
              autoFocus
            />
          </div>

          {error ? (
            <p className="text-sm text-red-600" role="alert">
              {error}
            </p>
          ) : null}

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              className="admin-btn-secondary"
              onClick={handleClose}
              disabled={saving}
            >
              Cancel
            </button>
            <button type="submit" className="admin-btn-primary" disabled={saving}>
              {saving ? "Creating…" : "Create option"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
