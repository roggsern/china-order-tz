"use client";

import { useState } from "react";
import {
  AdminCatalogApiError,
  createAdminBrand,
  type AdminBrand,
} from "@/lib/api/admin-catalog";

type AdminInlineCreateBrandModalProps = {
  open: boolean;
  categoryIds?: string[];
  onClose: () => void;
  onCreated: (brand: AdminBrand) => void;
};

export function AdminInlineCreateBrandModal({
  open,
  categoryIds = [],
  onClose,
  onCreated,
}: AdminInlineCreateBrandModalProps) {
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [logo, setLogo] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!open) {
    return null;
  }

  const reset = () => {
    setName("");
    setDescription("");
    setLogo("");
    setIsActive(true);
    setError(null);
  };

  const handleClose = () => {
    if (saving) return;
    reset();
    onClose();
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!name.trim()) {
      setError("Brand name is required.");
      return;
    }

    setSaving(true);
    setError(null);
    try {
      const brand = await createAdminBrand({
        name: name.trim(),
        description: description.trim() || null,
        logo: logo.trim() || null,
        is_active: isActive,
        category_ids: categoryIds.length > 0 ? categoryIds : undefined,
      });
      reset();
      onCreated(brand);
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to create brand.",
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
        aria-labelledby="create-brand-title"
        className="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-5 shadow-xl"
      >
        <h2 id="create-brand-title" className="text-lg font-bold text-zinc-900">
          Create new brand
        </h2>
        <p className="mt-1 text-sm text-zinc-500">
          The new brand will be selected automatically after creation.
        </p>

        <form className="mt-4 space-y-3" onSubmit={(event) => void handleSubmit(event)}>
          <div>
            <label className="admin-label" htmlFor="inline-brand-name">
              Name *
            </label>
            <input
              id="inline-brand-name"
              className="admin-input mt-1.5"
              value={name}
              onChange={(event) => setName(event.target.value)}
              autoFocus
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="inline-brand-logo">
              Logo URL (optional)
            </label>
            <input
              id="inline-brand-logo"
              className="admin-input mt-1.5"
              value={logo}
              onChange={(event) => setLogo(event.target.value)}
              placeholder="https://…"
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="inline-brand-description">
              Description (optional)
            </label>
            <textarea
              id="inline-brand-description"
              className="admin-input mt-1.5 min-h-[72px]"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
            />
          </div>
          <label className="flex items-center gap-2 text-sm text-zinc-700">
            <input
              type="checkbox"
              checked={isActive}
              onChange={(event) => setIsActive(event.target.checked)}
            />
            Active
          </label>

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
              {saving ? "Creating…" : "Create brand"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
