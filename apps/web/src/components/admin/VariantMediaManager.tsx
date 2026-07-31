"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  AdminCatalogApiError,
  deleteAdminProductMedia,
  fetchAdminProductMedia,
  setAdminProductMediaPrimary,
  updateAdminProductMedia,
  uploadAdminProductMediaImage,
  type AdminProductMedia,
} from "@/lib/api/admin-catalog";
import {
  VARIANT_MEDIA_EMPTY_STATE,
  buildVariantMediaUploadOptions,
  countVariantImages,
  formatVariantMediaEditingLabel,
} from "@/lib/admin/variant-media";
import {
  ADMIN_PRODUCT_MEDIA_ACCEPT,
  filterAcceptedProductMediaFiles,
  productMediaDropActiveClass,
  validateProductMediaUpload,
} from "@/lib/admin/product-media-upload";

type VariantMediaManagerProps = {
  productId: string;
  variantId: string;
  variantLabel: string;
  canUpdate: boolean;
  onClose: () => void;
  onChanged?: (imageCount: number) => void;
};

export function VariantMediaManager({
  productId,
  variantId,
  variantLabel,
  canUpdate,
  onClose,
  onChanged,
}: VariantMediaManagerProps) {
  const [items, setItems] = useState<AdminProductMedia[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  const onChangedRef = useRef(onChanged);
  onChangedRef.current = onChanged;

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const next = await fetchAdminProductMedia(productId, {
        productVariantId: variantId,
      });
      setItems(next);
      onChangedRef.current?.(countVariantImages(next));
    } catch (err) {
      setItems([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load variant media.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [productId, variantId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const images = useMemo(
    () => items.filter((item) => item.type === "image").sort((a, b) => a.sortOrder - b.sortOrder),
    [items],
  );
  const primary = images.find((item) => item.isPrimary) ?? images[0] ?? null;
  const editingLabel = formatVariantMediaEditingLabel(variantLabel);

  const run = async (action: () => Promise<void>) => {
    if (!canUpdate) {
      setError("You need catalog.update permission to manage variant images.");
      return;
    }

    setBusy(true);
    setError(null);
    try {
      await action();
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Variant media action failed.",
      );
    } finally {
      setBusy(false);
    }
  };

  const uploadFiles = async (incoming: File[]) => {
    if (!canUpdate) {
      setError("You need catalog.update permission to manage variant images.");
      return;
    }

    const { accepted, error: validationError } = validateProductMediaUpload(incoming);
    if (validationError) {
      setError(validationError);
    }
    if (accepted.length === 0) {
      return;
    }

    await run(async () => {
      for (let index = 0; index < accepted.length; index += 1) {
        const options = buildVariantMediaUploadOptions({
          productVariantId: variantId,
          variantLabel,
          existingImageCount: images.length,
          fileIndex: index,
        });
        await uploadAdminProductMediaImage(productId, accepted[index], options);
      }
    });
  };

  const handleUpload = async (fileList: FileList | null) => {
    await uploadFiles(filterAcceptedProductMediaFiles(fileList));
  };

  const moveImage = async (media: AdminProductMedia, direction: -1 | 1) => {
    const ordered = [...images];
    const index = ordered.findIndex((item) => item.id === media.id);
    const target = index + direction;
    if (index < 0 || target < 0 || target >= ordered.length) return;

    const swapped = [...ordered];
    const current = swapped[index];
    swapped[index] = swapped[target];
    swapped[target] = current;

    await run(async () => {
      await Promise.all(
        swapped.map((item, sortOrder) =>
          updateAdminProductMedia(productId, item.id, { sort_order: sortOrder }),
        ),
      );
    });
  };

  return (
    <div className="rounded-lg border border-zinc-200 bg-white p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="whitespace-pre-line text-sm font-semibold text-zinc-900">{editingLabel}</p>
          <p className="mt-1 text-xs text-zinc-500">
            Variant-specific images. When empty, product images are used as fallback.
          </p>
        </div>
        <button
          type="button"
          className="rounded px-2 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-100"
          onClick={onClose}
        >
          Close
        </button>
      </div>

      {error ? (
        <div className="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {canUpdate ? (
        <div
          className={`mt-4 rounded-xl border-2 border-dashed p-5 transition ${productMediaDropActiveClass(isDragging)}`}
          onDragEnter={(event) => {
            event.preventDefault();
            setIsDragging(true);
          }}
          onDragOver={(event) => {
            event.preventDefault();
            setIsDragging(true);
          }}
          onDragLeave={(event) => {
            event.preventDefault();
            if (event.currentTarget.contains(event.relatedTarget as Node)) {
              return;
            }
            setIsDragging(false);
          }}
          onDrop={(event) => {
            event.preventDefault();
            setIsDragging(false);
            if (busy) return;
            void uploadFiles(filterAcceptedProductMediaFiles(event.dataTransfer.files));
          }}
        >
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 className="text-sm font-semibold text-zinc-900">Upload images</h3>
              <p className="mt-1 text-xs text-zinc-500">
                Drag and drop images here, or choose files. JPG, PNG, WEBP — max 5MB each.
              </p>
            </div>
            <label className="admin-btn-primary cursor-pointer">
              <span>{busy ? "Uploading…" : "Choose images"}</span>
              <input
                type="file"
                accept={ADMIN_PRODUCT_MEDIA_ACCEPT}
                multiple
                disabled={busy}
                className="sr-only"
                onChange={(event) => {
                  void handleUpload(event.target.files);
                  event.target.value = "";
                }}
              />
            </label>
          </div>
          <p className="mt-4 text-center text-xs font-medium text-zinc-500">
            {isDragging ? "Drop images to upload" : "Drop zone ready"}
          </p>
        </div>
      ) : (
        <p className="mt-4 text-xs text-zinc-500">
          View only — catalog.update is required to upload, reorder, or delete images.
        </p>
      )}

      {isLoading ? (
        <div className="mt-4 space-y-3">
          <div className="h-28 animate-pulse rounded-xl bg-zinc-100" />
          <div className="h-20 animate-pulse rounded-xl bg-zinc-100" />
        </div>
      ) : (
        <>
          <section className="mt-4">
            <h3 className="text-sm font-semibold text-zinc-900">Primary image</h3>
            {primary ? (
              <div className="mt-2 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/40 p-3">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={primary.thumbnailUrl || primary.url}
                  alt={primary.altText || variantLabel}
                  className="h-24 w-24 rounded object-cover"
                />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-zinc-900">
                    {primary.title || "Primary image"}
                    {primary.isPrimary ? (
                      <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                        Primary
                      </span>
                    ) : null}
                  </p>
                  <p className="truncate text-xs text-zinc-500">{primary.url}</p>
                </div>
              </div>
            ) : (
              <p className="mt-2 text-sm text-zinc-500">{VARIANT_MEDIA_EMPTY_STATE}</p>
            )}
          </section>

          <section className="mt-4">
            <h3 className="text-sm font-semibold text-zinc-900">Gallery</h3>
            {images.length === 0 ? (
              <p className="mt-2 text-sm text-zinc-500">{VARIANT_MEDIA_EMPTY_STATE}</p>
            ) : (
              <ul className="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {images.map((image, index) => (
                  <li
                    key={image.id}
                    className="overflow-hidden rounded-xl border border-zinc-200 bg-white"
                  >
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={image.thumbnailUrl || image.url}
                      alt={image.altText || ""}
                      className="h-36 w-full object-cover"
                    />
                    <div className="space-y-2 p-3">
                      <p className="text-sm font-medium text-zinc-900">
                        {image.title || `Image ${index + 1}`}
                        {image.isPrimary ? (
                          <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                            Primary
                          </span>
                        ) : null}
                      </p>
                      <p className="text-[11px] text-zinc-500">Order {image.sortOrder + 1}</p>
                      {canUpdate ? (
                        <div className="flex flex-wrap gap-1">
                          <button
                            type="button"
                            className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50"
                            disabled={busy || index === 0}
                            onClick={() => void moveImage(image, -1)}
                          >
                            Earlier
                          </button>
                          <button
                            type="button"
                            className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50"
                            disabled={busy || index === images.length - 1}
                            onClick={() => void moveImage(image, 1)}
                          >
                            Later
                          </button>
                          {!image.isPrimary ? (
                            <button
                              type="button"
                              className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50"
                              disabled={busy}
                              onClick={() =>
                                void run(async () => {
                                  await setAdminProductMediaPrimary(productId, image.id);
                                })
                              }
                            >
                              Set primary
                            </button>
                          ) : null}
                          <button
                            type="button"
                            className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                            disabled={busy}
                            onClick={() =>
                              void run(async () => {
                                await deleteAdminProductMedia(productId, image.id);
                              })
                            }
                          >
                            Delete
                          </button>
                        </div>
                      ) : null}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}
    </div>
  );
}
