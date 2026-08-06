"use client";

import { useEffect, useMemo, useState } from "react";
import {
  ADMIN_PRODUCT_MEDIA_ACCEPT,
  validateProductMediaUpload,
} from "@/lib/admin/product-media-upload";
import {
  countVariantsForAttributeOption,
  formatAttributeOptionApplySummary,
  pickDefaultAttributeForImageApply,
} from "@/lib/admin/variant-attribute-image-apply";
import {
  AdminCatalogApiError,
  applyAdminProductMediaToAttributeOption,
  type AdminProductVariant,
  type AdminVariantAttribute,
} from "@/lib/api/admin-catalog";

type VariantAttributeImageApplyProps = {
  productId: string;
  attributes: AdminVariantAttribute[];
  variants: AdminProductVariant[];
  canUpdate: boolean;
  disabled?: boolean;
  onApplied: (summary: string) => void;
  onError: (message: string) => void;
};

export function VariantAttributeImageApply({
  productId,
  attributes,
  variants,
  canUpdate,
  disabled = false,
  onApplied,
  onError,
}: VariantAttributeImageApplyProps) {
  const defaultAttribute = useMemo(
    () => pickDefaultAttributeForImageApply(attributes),
    [attributes],
  );
  const [attributeId, setAttributeId] = useState(
    () => defaultAttribute?.catalogAttributeId ?? "",
  );
  const [optionId, setOptionId] = useState(
    () => defaultAttribute?.options[0]?.id ?? "",
  );
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const nextDefault = pickDefaultAttributeForImageApply(attributes);
    setAttributeId((current) => {
      if (attributes.some((attr) => attr.catalogAttributeId === current)) {
        return current;
      }
      return nextDefault?.catalogAttributeId ?? "";
    });
  }, [attributes]);

  const selectedAttribute = useMemo(
    () => attributes.find((attr) => attr.catalogAttributeId === attributeId) ?? null,
    [attributes, attributeId],
  );

  useEffect(() => {
    if (!selectedAttribute) {
      setOptionId("");
      return;
    }
    setOptionId((current) => {
      if (selectedAttribute.options.some((option) => option.id === current)) {
        return current;
      }
      return selectedAttribute.options[0]?.id ?? "";
    });
  }, [selectedAttribute]);

  const selectedOption = selectedAttribute?.options.find((option) => option.id === optionId);
  const matchedCount = countVariantsForAttributeOption(variants, optionId);
  const selectableAttributes = attributes.filter((attr) => attr.options.length > 0);

  if (selectableAttributes.length === 0 || variants.length === 0) {
    return null;
  }

  const handleApply = async () => {
    if (!canUpdate) {
      onError("You need catalog.update permission to apply variant images.");
      return;
    }
    if (!optionId || !selectedOption) {
      onError("Select an attribute option (for example Color: Blue).");
      return;
    }
    if (!file) {
      onError("Choose one image to apply to all matching variants.");
      return;
    }

    const validation = validateProductMediaUpload([file]);
    if (validation.error || validation.accepted.length === 0) {
      onError(validation.error ?? "Unsupported image file.");
      return;
    }

    setBusy(true);
    try {
      const result = await applyAdminProductMediaToAttributeOption(productId, file, {
        catalogAttributeOptionId: optionId,
        altText: selectedOption.value,
        title: `${selectedOption.value} image`,
      });
      setFile(null);
      onApplied(
        formatAttributeOptionApplySummary({
          optionValue: result.optionValue || selectedOption.value,
          attributeName: result.attributeName ?? selectedAttribute?.name,
          matchedCount: result.matchedVariantCount,
          appliedCount: result.appliedCount,
          skippedCount: result.skippedCount,
        }),
      );
    } catch (err) {
      onError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to apply image to attribute option.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="rounded-lg border border-zinc-200 p-4">
      <h3 className="text-sm font-semibold text-zinc-900">Bulk attribute images</h3>
      <p className="mt-1 text-xs text-zinc-500">
        Upload one image for a Color (or other attribute) and apply it to every
        variant that shares that option. Variants that already have images are
        skipped so manual overrides stay intact.
      </p>

      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        <div>
          <label className="admin-label" htmlFor="bulk-attr-image-attribute">
            Attribute
          </label>
          <select
            id="bulk-attr-image-attribute"
            className="admin-input mt-1"
            value={attributeId}
            disabled={busy || disabled || !canUpdate}
            onChange={(event) => setAttributeId(event.target.value)}
          >
            {selectableAttributes.map((attr) => (
              <option key={attr.catalogAttributeId} value={attr.catalogAttributeId}>
                {attr.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="admin-label" htmlFor="bulk-attr-image-option">
            Option
          </label>
          <select
            id="bulk-attr-image-option"
            className="admin-input mt-1"
            value={optionId}
            disabled={busy || disabled || !canUpdate || !selectedAttribute}
            onChange={(event) => setOptionId(event.target.value)}
          >
            {(selectedAttribute?.options ?? []).map((option) => (
              <option key={option.id} value={option.id}>
                {option.value}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="mt-3 flex flex-wrap items-end gap-3">
        <div className="min-w-[12rem] flex-1">
          <label className="admin-label" htmlFor="bulk-attr-image-file">
            Image
          </label>
          <input
            id="bulk-attr-image-file"
            type="file"
            accept={ADMIN_PRODUCT_MEDIA_ACCEPT}
            className="admin-input mt-1"
            disabled={busy || disabled || !canUpdate}
            onChange={(event) => {
              const incoming = Array.from(event.target.files ?? []);
              const validation = validateProductMediaUpload(incoming);
              if (validation.error || validation.accepted.length === 0) {
                setFile(null);
                if (validation.error) {
                  onError(validation.error);
                }
                event.target.value = "";
                return;
              }
              setFile(validation.accepted[0] ?? null);
              event.target.value = "";
            }}
          />
        </div>
        <button
          type="button"
          className="rounded-md bg-zinc-900 px-3 py-2 text-xs font-medium text-white disabled:opacity-50"
          disabled={busy || disabled || !canUpdate || !file || !optionId || matchedCount === 0}
          onClick={() => void handleApply()}
        >
          {busy ? "Applying…" : "Apply to matching variants"}
        </button>
      </div>

      <p className="mt-2 text-xs text-zinc-500">
        {selectedOption
          ? `${matchedCount} variant${matchedCount === 1 ? "" : "s"} match ${selectedAttribute?.name ?? "option"}: ${selectedOption.value}.`
          : "Select an option to see matching variants."}
      </p>
    </div>
  );
}
