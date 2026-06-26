"use client";

import { useState } from "react";
import type { Product, ProductImage } from "@/lib/types/catalog";
import { getProductGalleryImages } from "@/lib/catalog/product-images";
import { ProductImageDisplay } from "./ProductImageDisplay";

interface ProductGalleryProps {
  product: Pick<Product, "images" | "image" | "name" | "emoji" | "gradient">;
  /** @deprecated Pass `product` instead */
  images?: ProductImage[];
  productName?: string;
  fallbackEmoji?: string;
  fallbackGradient?: string;
}

export function ProductGallery({
  product,
  images: legacyImages,
  productName,
  fallbackEmoji,
  fallbackGradient,
}: ProductGalleryProps) {
  const galleryProduct = legacyImages?.length
    ? {
        ...product,
        images: legacyImages,
        name: productName ?? product.name,
      }
    : product;

  const images = getProductGalleryImages(galleryProduct);
  const name = productName ?? product.name;
  const emoji = fallbackEmoji ?? product.emoji;
  const gradient = fallbackGradient ?? product.gradient;

  const [activeIndex, setActiveIndex] = useState(0);
  const activeImage = images[activeIndex] ?? images[0];

  if (!activeImage) return null;

  return (
    <div className="space-y-4">
      <div className="overflow-hidden rounded-3xl shadow-[0_8px_32px_rgba(0,0,0,0.08)] ring-1 ring-zinc-200/80">
        <ProductImageDisplay
          image={activeImage}
          fallbackEmoji={emoji}
          fallbackGradient={gradient}
          className="aspect-square sm:aspect-[4/5]"
          emojiClassName="text-8xl drop-shadow-lg sm:text-9xl"
        />
      </div>

      {images.length > 1 && (
        <div className="flex gap-3 overflow-x-auto pb-1">
          {images.map((image, index) => (
            <button
              key={image.id}
              type="button"
              onClick={() => setActiveIndex(index)}
              className={`h-20 w-20 shrink-0 overflow-hidden rounded-xl transition ring-2 ${
                index === activeIndex
                  ? "ring-[#c9a227] ring-offset-2"
                  : "ring-transparent hover:ring-zinc-200"
              }`}
              aria-label={`View ${name} image ${index + 1}`}
              aria-current={index === activeIndex}
            >
              <ProductImageDisplay
                image={image}
                fallbackEmoji={emoji}
                fallbackGradient={gradient}
                className="h-full w-full"
                emojiClassName="text-2xl"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
