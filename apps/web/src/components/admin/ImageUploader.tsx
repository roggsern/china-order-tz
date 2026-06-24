"use client";

import { useRef, useState } from "react";
import type { ProductImage } from "@/lib/types/catalog";
import { CloseIcon, UploadIcon } from "@/components/home/icons";

interface ImageUploaderProps {
  images: ProductImage[];
  onChange: (images: ProductImage[]) => void;
  productName?: string;
  gradient?: string;
  emoji?: string;
}

export function ImageUploader({
  images,
  onChange,
  productName = "Product",
  gradient = "from-zinc-500 to-zinc-700",
  emoji = "📦",
}: ImageUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);

  const addFiles = (files: FileList | null) => {
    if (!files?.length) return;

    const nextId = Math.max(0, ...images.map((img) => img.id), 0);
    const newImages: ProductImage[] = [];

    Array.from(files).forEach((file, index) => {
      if (!file.type.startsWith("image/")) return;
      newImages.push({
        id: nextId + index + 1,
        emoji,
        gradient,
        alt: `${productName} image ${images.length + index + 1}`,
        url: URL.createObjectURL(file),
      });
    });

    if (newImages.length) {
      onChange([...images, ...newImages]);
    }
  };

  const removeImage = (id: number) => {
    const target = images.find((img) => img.id === id);
    if (target?.url) URL.revokeObjectURL(target.url);
    onChange(images.filter((img) => img.id !== id));
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    addFiles(e.dataTransfer.files);
  };

  return (
    <div className="space-y-4">
      {images.length > 0 && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {images.map((image, index) => (
            <div
              key={image.id}
              className="group relative aspect-square overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50"
            >
              {image.url ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={image.url} alt={image.alt} className="h-full w-full object-cover" />
              ) : (
                <div
                  className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${image.gradient || gradient}`}
                >
                  <span className="text-4xl">{image.emoji || emoji}</span>
                </div>
              )}
              {index === 0 && (
                <span className="absolute left-2 top-2 rounded bg-zinc-900/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                  Main
                </span>
              )}
              <button
                type="button"
                onClick={() => removeImage(image.id)}
                className="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-md bg-white/90 text-zinc-600 opacity-0 shadow-sm transition hover:bg-white hover:text-red-600 group-hover:opacity-100"
                aria-label={`Remove image ${index + 1}`}
              >
                <CloseIcon className="h-4 w-4" />
              </button>
            </div>
          ))}
        </div>
      )}

      <div
        onDragOver={(e) => {
          e.preventDefault();
          setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => inputRef.current?.click()}
        className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-8 transition ${
          dragOver
            ? "border-[#c9a227] bg-[#c9a227]/5"
            : "border-zinc-300 bg-zinc-50/50 hover:border-zinc-400 hover:bg-zinc-50"
        }`}
      >
        <UploadIcon className="h-8 w-8 text-zinc-400" />
        <p className="mt-2 text-sm font-medium text-zinc-700">
          Drop images here or click to upload
        </p>
        <p className="mt-1 text-xs text-zinc-500">PNG, JPG, WEBP up to 10MB each</p>
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          multiple
          className="hidden"
          onChange={(e) => {
            addFiles(e.target.files);
            e.target.value = "";
          }}
        />
      </div>
    </div>
  );
}
