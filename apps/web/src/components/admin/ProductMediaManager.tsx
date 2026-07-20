"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminProductMediaVideo,
  deleteAdminProductMedia,
  fetchAdminProductMedia,
  setAdminProductMediaPrimary,
  updateAdminProductMedia,
  uploadAdminProductMediaImage,
  type AdminProductMedia,
} from "@/lib/api/admin-catalog";

type ProductMediaManagerProps = {
  productId: string;
  productName: string;
};

export function ProductMediaManager({ productId, productName }: ProductMediaManagerProps) {
  const [items, setItems] = useState<AdminProductMedia[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [videoUrl, setVideoUrl] = useState("");
  const [videoTitle, setVideoTitle] = useState("");

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      setItems(await fetchAdminProductMedia(productId));
    } catch (err) {
      setItems([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load product media.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const images = useMemo(
    () => items.filter((item) => item.type === "image").sort((a, b) => a.sortOrder - b.sortOrder),
    [items],
  );
  const videos = useMemo(
    () => items.filter((item) => item.type === "video").sort((a, b) => a.sortOrder - b.sortOrder),
    [items],
  );
  const primary = images.find((item) => item.isPrimary) ?? images[0] ?? null;

  const run = async (action: () => Promise<void>) => {
    setBusy(true);
    setError(null);
    try {
      await action();
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Media action failed.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleUpload = async (fileList: FileList | null) => {
    if (!fileList || fileList.length === 0) return;
    await run(async () => {
      const files = Array.from(fileList);
      for (let index = 0; index < files.length; index += 1) {
        await uploadAdminProductMediaImage(productId, files[index], {
          title: `${productName} image`,
          isPrimary: images.length === 0 && index === 0,
          sortOrder: images.length + index,
        });
      }
    });
  };

  const handleAddVideo = async () => {
    if (!videoUrl.trim()) {
      setError("Video URL is required.");
      return;
    }
    await run(async () => {
      await createAdminProductMediaVideo(productId, videoUrl.trim(), {
        title: videoTitle.trim() || `${productName} video`,
      });
      setVideoUrl("");
      setVideoTitle("");
    });
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
    <div className="space-y-5">
      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <div className="rounded-lg border border-zinc-200 p-4">
        <h3 className="text-sm font-semibold text-zinc-900">Upload images</h3>
        <p className="mt-1 text-xs text-zinc-500">JPG, JPEG, PNG, WEBP — max 5MB each.</p>
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
          multiple
          disabled={busy}
          className="mt-3 block w-full text-xs text-zinc-600"
          onChange={(event) => {
            void handleUpload(event.target.files);
            event.target.value = "";
          }}
        />
      </div>

      <div className="rounded-lg border border-zinc-200 p-4">
        <h3 className="text-sm font-semibold text-zinc-900">Add video URL</h3>
        <p className="mt-1 text-xs text-zinc-500">YouTube or Vimeo links supported.</p>
        <div className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
          <input
            className="admin-input"
            placeholder="https://www.youtube.com/watch?v=…"
            value={videoUrl}
            onChange={(event) => setVideoUrl(event.target.value)}
          />
          <button
            type="button"
            className="admin-btn-primary"
            disabled={busy}
            onClick={() => void handleAddVideo()}
          >
            Add video
          </button>
        </div>
        <input
          className="admin-input mt-2"
          placeholder="Video title (optional)"
          value={videoTitle}
          onChange={(event) => setVideoTitle(event.target.value)}
        />
      </div>

      {isLoading ? (
        <p className="text-sm text-zinc-500">Loading media…</p>
      ) : (
        <>
          <section>
            <h3 className="text-sm font-semibold text-zinc-900">Primary image</h3>
            {primary ? (
              <div className="mt-2 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/40 p-3">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={primary.thumbnailUrl || primary.url}
                  alt={primary.altText || productName}
                  className="h-24 w-24 rounded object-cover"
                />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-zinc-900">
                    {primary.title || "Primary image"}
                  </p>
                  <p className="text-xs text-zinc-500 truncate">{primary.url}</p>
                </div>
              </div>
            ) : (
              <p className="mt-2 text-sm text-zinc-500">No primary image yet.</p>
            )}
          </section>

          <section>
            <h3 className="text-sm font-semibold text-zinc-900">Gallery</h3>
            {images.length === 0 ? (
              <p className="mt-2 text-sm text-zinc-500">No gallery images.</p>
            ) : (
              <ul className="mt-2 divide-y divide-zinc-100 rounded-lg border border-zinc-200">
                {images.map((image, index) => (
                  <li key={image.id} className="flex flex-wrap items-center gap-3 px-3 py-2">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={image.thumbnailUrl || image.url}
                      alt={image.altText || ""}
                      className="h-14 w-14 rounded object-cover"
                    />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm text-zinc-900">
                        {image.title || `Image ${index + 1}`}
                        {image.isPrimary ? (
                          <span className="ml-2 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">
                            Primary
                          </span>
                        ) : null}
                        {!image.isActive ? (
                          <span className="ml-2 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-zinc-500">
                            Inactive
                          </span>
                        ) : null}
                      </p>
                      <p className="text-[11px] text-zinc-500">sort {image.sortOrder}</p>
                    </div>
                    <div className="flex flex-wrap gap-1">
                      <button
                        type="button"
                        className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                        disabled={busy || index === 0}
                        onClick={() => void moveImage(image, -1)}
                      >
                        Up
                      </button>
                      <button
                        type="button"
                        className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                        disabled={busy || index === images.length - 1}
                        onClick={() => void moveImage(image, 1)}
                      >
                        Down
                      </button>
                      {!image.isPrimary ? (
                        <button
                          type="button"
                          className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
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
                        className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                        disabled={busy}
                        onClick={() =>
                          void run(async () => {
                            await updateAdminProductMedia(productId, image.id, {
                              is_active: !image.isActive,
                            });
                          })
                        }
                      >
                        {image.isActive ? "Deactivate" : "Activate"}
                      </button>
                      <button
                        type="button"
                        className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
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
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section>
            <h3 className="text-sm font-semibold text-zinc-900">Videos</h3>
            {videos.length === 0 ? (
              <p className="mt-2 text-sm text-zinc-500">No videos.</p>
            ) : (
              <ul className="mt-2 divide-y divide-zinc-100 rounded-lg border border-zinc-200">
                {videos.map((video) => (
                  <li key={video.id} className="flex flex-wrap items-center gap-3 px-3 py-2">
                    {video.thumbnailUrl ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img
                        src={video.thumbnailUrl}
                        alt=""
                        className="h-14 w-20 rounded object-cover"
                      />
                    ) : (
                      <div className="flex h-14 w-20 items-center justify-center rounded bg-zinc-100 text-xs text-zinc-500">
                        Video
                      </div>
                    )}
                    <div className="min-w-0 flex-1">
                      <p className="text-sm text-zinc-900">{video.title || "Video"}</p>
                      <a
                        href={video.url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-[11px] text-[#8b6914] hover:underline"
                      >
                        {video.url}
                      </a>
                    </div>
                    <div className="flex gap-1">
                      <button
                        type="button"
                        className="rounded px-2 py-1 text-[11px] font-medium text-zinc-600 hover:bg-zinc-100"
                        disabled={busy}
                        onClick={() =>
                          void run(async () => {
                            await updateAdminProductMedia(productId, video.id, {
                              is_active: !video.isActive,
                            });
                          })
                        }
                      >
                        {video.isActive ? "Deactivate" : "Activate"}
                      </button>
                      <button
                        type="button"
                        className="rounded px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50"
                        disabled={busy}
                        onClick={() =>
                          void run(async () => {
                            await deleteAdminProductMedia(productId, video.id);
                          })
                        }
                      >
                        Delete
                      </button>
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
