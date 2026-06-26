"use client";

import { useRef, useState } from "react";
import type { ProductImage } from "@/lib/types/catalog";
import { resolveImageUrl } from "@/lib/catalog/product-images";
import { CloseIcon, UploadIcon } from "@/components/home/icons";

interface ImageUploaderProps {
  images: ProductImage[];
  thumbnailImageId: number | null;
  onChange: (images: ProductImage[]) => void;
  onThumbnailChange: (id: number | null) => void;
  productName?: string;
  gradient?: string;
  emoji?: string;
}

async function uploadProductImage(file: File): Promise<string> {
  const formData = new FormData();
  formData.append("file", file);

  const response = await fetch("/api/uploads", {
    method: "POST",
    body: formData,
  });

  if (!response.ok) {
    const payload = (await response.json().catch(() => null)) as { error?: string } | null;
    throw new Error(payload?.error ?? "Image upload failed");
  }

  const payload = (await response.json()) as { url?: string };
  if (!payload.url?.trim()) {
    throw new Error("Upload did not return a valid image path");
  }

  return payload.url.trim();
}

export function ImageUploader({
  images,
  thumbnailImageId,
  onChange,
  onThumbnailChange,
  productName = "Product",
  gradient = "from-zinc-500 to-zinc-700",
  emoji = "📦",
}: ImageUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);
  const [draggingId, setDraggingId] = useState<number | null>(null);
  const [uploadingCount, setUploadingCount] = useState(0);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const addFiles = async (files: FileList | null) => {
    if (!files?.length) return;

    setUploadError(null);
    const imageFiles = Array.from(files).filter((file) => file.type.startsWith("image/"));
    if (!imageFiles.length) return;

    let nextId = Math.max(0, ...images.map((image) => image.id), 0);
    const uploadedImages: ProductImage[] = [];

    setUploadingCount((count) => count + imageFiles.length);

    for (const file of imageFiles) {
      try {
        const url = await uploadProductImage(file);
        nextId += 1;
        uploadedImages.push({
          id: nextId,
          emoji,
          gradient,
          alt: `${productName} image ${images.length + uploadedImages.length + 1}`,
          url,
        });
      } catch (error) {
        setUploadError(error instanceof Error ? error.message : "Image upload failed");
      } finally {
        setUploadingCount((count) => Math.max(0, count - 1));
      }
    }

    if (uploadedImages.length) {
      const merged = [...images, ...uploadedImages];
      onChange(merged);
      if (thumbnailImageId == null) {
        onThumbnailChange(merged[0]?.id ?? null);
      }
    }
  };

  const removeImage = (id: number) => {
    const next = images.filter((image) => image.id !== id);
    onChange(next);

    if (thumbnailImageId === id) {
      onThumbnailChange(next[0]?.id ?? null);
    }
  };

  const reorderImages = (sourceId: number, targetId: number) => {
    if (sourceId === targetId) return;

    const sourceIndex = images.findIndex((image) => image.id === sourceId);
    const targetIndex = images.findIndex((image) => image.id === targetId);
    if (sourceIndex < 0 || targetIndex < 0) return;

    const next = [...images];
    const [moved] = next.splice(sourceIndex, 1);
    next.splice(targetIndex, 0, moved);
    onChange(next);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    void addFiles(e.dataTransfer.files);
  };

  return (
    <div className="space-y-4">
      {uploadError && (
        <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
          {uploadError}
        </p>
      )}

      {images.length > 0 && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {images.map((image, index) => {
            const isThumbnail = (thumbnailImageId ?? images[0]?.id) === image.id;
            const previewUrl = image.url ? resolveImageUrl(image.url) : undefined;

            return (
              <div
                key={image.id}
                draggable
                onDragStart={() => setDraggingId(image.id)}
                onDragOver={(event) => event.preventDefault()}
                onDrop={() => {
                  if (draggingId != null) reorderImages(draggingId, image.id);
                  setDraggingId(null);
                }}
                onDragEnd={() => setDraggingId(null)}
                className={`group relative aspect-square cursor-grab overflow-hidden rounded-lg border bg-zinc-50 active:cursor-grabbing ${
                  isThumbnail ? "border-[#c9a227] ring-2 ring-[#c9a227]/30" : "border-zinc-200"
                }`}
              >
                {previewUrl ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={previewUrl} alt={image.alt} className="h-full w-full object-cover" />
                ) : (
                  <div
                    className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${image.gradient || gradient}`}
                  >
                    <span className="text-4xl">{image.emoji || emoji}</span>
                  </div>
                )}

                <div className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-1 bg-gradient-to-t from-zinc-900/80 to-transparent p-2 pt-8">
                  <button
                    type="button"
                    onClick={() => onThumbnailChange(image.id)}
                    className={`rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide transition ${
                      isThumbnail
                        ? "bg-[#c9a227] text-zinc-900"
                        : "bg-white/90 text-zinc-700 hover:bg-white"
                    }`}
                  >
                    {isThumbnail ? "Thumbnail" : "Set thumb"}
                  </button>
                  <span className="text-[10px] font-medium text-white/80">#{index + 1}</span>
                </div>

                <button
                  type="button"
                  onClick={() => removeImage(image.id)}
                  className="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-md bg-white/90 text-zinc-600 opacity-0 shadow-sm transition hover:bg-white hover:text-red-600 group-hover:opacity-100"
                  aria-label={`Remove image ${index + 1}`}
                >
                  <CloseIcon className="h-4 w-4" />
                </button>
              </div>
            );
          })}
        </div>
      )}

      <div
        onDragOver={(event) => {
          event.preventDefault();
          setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => inputRef.current?.click()}
        className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 transition ${
          dragOver
            ? "border-[#c9a227] bg-[#c9a227]/5"
            : "border-zinc-300 bg-zinc-50/50 hover:border-zinc-400 hover:bg-zinc-50"
        } ${uploadingCount > 0 ? "pointer-events-none opacity-60" : ""}`}
      >
        <UploadIcon className="h-8 w-8 text-zinc-400" />
        <p className="mt-2 text-sm font-medium text-zinc-700">
          {uploadingCount > 0 ? `Uploading ${uploadingCount} image(s)…` : "Drop images here or click to upload"}
        </p>
        <p className="mt-1 text-xs text-zinc-500">
          Drag tiles to reorder · Select thumbnail · PNG, JPG, WEBP
        </p>
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          multiple
          className="hidden"
          onChange={(event) => {
            void addFiles(event.target.files);
            event.target.value = "";
          }}
        />
      </div>
    </div>
  );
}
