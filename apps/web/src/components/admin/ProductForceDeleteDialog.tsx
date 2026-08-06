"use client";

import { useEffect, useState } from "react";
import {
  AdminCatalogApiError,
  fetchProductForceDeleteEligibility,
  forceDeleteAdminCatalogProduct,
  type AdminCatalogProduct,
  type ProductForceDeleteEligibility,
} from "@/lib/api/admin-catalog";

type Props = {
  product: AdminCatalogProduct;
  open: boolean;
  onClose: () => void;
  onDeleted: (message: string) => void;
};

export function ProductForceDeleteDialog({ product, open, onClose, onDeleted }: Props) {
  const [eligibility, setEligibility] = useState<ProductForceDeleteEligibility | null>(null);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [confirmation, setConfirmation] = useState("");

  useEffect(() => {
    if (!open) {
      return;
    }

    let cancelled = false;
    setEligibility(null);
    setConfirmation("");
    setError(null);
    setLoading(true);

    void fetchProductForceDeleteEligibility(product.id)
      .then((result) => {
        if (!cancelled) {
          setEligibility(result);
        }
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setError(
            err instanceof AdminCatalogApiError
              ? err.message
              : "Unable to check permanent deletion eligibility.",
          );
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [open, product.id]);

  if (!open) {
    return null;
  }

  const phrase = eligibility?.confirmationPhrase ?? `DELETE ${product.name.toUpperCase()}`;
  const canSubmit =
    Boolean(eligibility?.canForceDelete) &&
    confirmation === phrase &&
    !submitting &&
    !loading;

  const handleConfirm = async () => {
    if (!canSubmit) {
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      const result = await forceDeleteAdminCatalogProduct(product.id, confirmation);
      onDeleted(result.message);
      onClose();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to permanently delete product.",
      );
    } finally {
      setSubmitting(false);
    }
  };

  const deps = eligibility?.deletableDependencies;

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="force-delete-title"
        className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-zinc-200 bg-white p-5 shadow-xl"
      >
        <h2 id="force-delete-title" className="text-base font-semibold text-red-700">
          Permanently delete product
        </h2>
        <p className="mt-2 text-sm text-zinc-700">
          This action is irreversible. Catalog children and owned media for{" "}
          <span className="font-medium text-zinc-900">{product.name}</span> will be removed.
        </p>
        <p className="mt-1 text-xs text-zinc-500">
          Slug: {product.slug}
          {product.sku ? ` · SKU: ${product.sku}` : ""}
        </p>

        {loading ? (
          <p className="mt-4 text-sm text-zinc-500">Checking dependencies…</p>
        ) : null}

        {error ? (
          <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {error}
          </div>
        ) : null}

        {eligibility && !eligibility.canForceDelete ? (
          <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
            <p className="text-sm font-medium text-amber-900">Permanent deletion blocked</p>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-amber-800">
              {eligibility.blockingDependencies.map((blocker) => (
                <li key={blocker.type}>
                  {blocker.message} ({blocker.count})
                </li>
              ))}
            </ul>
            <p className="mt-2 text-xs text-amber-800">
              You can still restore this product. Order history and snapshots stay intact.
            </p>
          </div>
        ) : null}

        {eligibility?.canForceDelete && deps ? (
          <div className="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-700">
            <p className="font-medium text-zinc-900">Will be removed</p>
            <ul className="mt-1 space-y-0.5">
              <li>{deps.variants} variant{deps.variants === 1 ? "" : "s"}</li>
              <li>{deps.variantPrices} variant price row{deps.variantPrices === 1 ? "" : "s"}</li>
              <li>
                {deps.productMedia + deps.productImages} media/image row
                {deps.productMedia + deps.productImages === 1 ? "" : "s"}
              </li>
              <li>Owned media files on disk (shared files are kept)</li>
            </ul>
          </div>
        ) : null}

        {eligibility?.canForceDelete ? (
          <label className="mt-4 block text-sm text-zinc-700">
            Type <span className="font-mono font-semibold text-zinc-900">{phrase}</span> to confirm
            <input
              className="admin-input mt-2 w-full font-mono"
              value={confirmation}
              onChange={(event) => setConfirmation(event.target.value)}
              autoComplete="off"
              spellCheck={false}
              placeholder={phrase}
            />
          </label>
        ) : null}

        <div className="mt-5 flex flex-wrap justify-end gap-2">
          <button type="button" className="admin-btn-secondary" onClick={onClose} disabled={submitting}>
            Cancel
          </button>
          <button
            type="button"
            className="rounded-lg bg-red-700 px-3 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-40"
            disabled={!canSubmit}
            onClick={() => void handleConfirm()}
          >
            {submitting ? "Deleting…" : "Permanently delete"}
          </button>
        </div>
      </div>
    </div>
  );
}
