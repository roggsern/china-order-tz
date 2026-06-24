"use client";

import { useState } from "react";
import type { ProductImage } from "@/lib/types/catalog";

interface ProductGalleryProps {
  images: ProductImage[];
  productName: string;
}

export function ProductGallery({ images, productName }: ProductGalleryProps) {
  const [activeIndex, setActiveIndex] = useState(0);
  const activeImage = images[activeIndex] ?? images[0];

  if (!activeImage) return null;

  return (
    <div className="space-y-4">
      <div
        className={`flex aspect-square items-center justify-center rounded-2xl bg-gradient-to-br ${activeImage.gradient} shadow-sm ring-1 ring-zinc-200/80`}
      >
        <span className="text-8xl drop-shadow-lg sm:text-9xl">{activeImage.emoji}</span>
      </div>

      {images.length > 1 && (
        <div className="flex gap-3 overflow-x-auto pb-1">
          {images.map((image, index) => (
            <button
              key={image.id}
              type="button"
              onClick={() => setActiveIndex(index)}
              className={`flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${image.gradient} transition ring-2 ${
                index === activeIndex
                  ? "ring-[#c9a227] ring-offset-2"
                  : "ring-transparent hover:ring-zinc-200"
              }`}
              aria-label={`View ${productName} image ${index + 1}`}
            >
              <span className="text-2xl">{image.emoji}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
