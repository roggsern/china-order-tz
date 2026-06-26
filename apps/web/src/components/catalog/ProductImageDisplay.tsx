"use client";

import { useState } from "react";
import type { ProductImage } from "@/lib/types/catalog";
import {
  PRODUCT_PLACEHOLDER_IMAGE,
  isPlaceholderImageUrl,
  resolveImageUrl,
} from "@/lib/catalog/product-images";

interface ProductImageDisplayProps {
  image: ProductImage;
  fallbackEmoji?: string;
  fallbackGradient?: string;
  className?: string;
  emojiClassName?: string;
}

export function ProductImageDisplay({
  image,
  fallbackEmoji,
  fallbackGradient,
  className = "",
  emojiClassName = "text-6xl",
}: ProductImageDisplayProps) {
  const emoji = image.emoji || fallbackEmoji || "📦";
  const gradient = image.gradient || fallbackGradient || "from-zinc-200 to-zinc-300";
  const [hasError, setHasError] = useState(false);

  const rawUrl = image.url?.trim();
  const hasRealUrl = Boolean(rawUrl && !isPlaceholderImageUrl(rawUrl));
  const resolvedUrl = hasRealUrl && !hasError ? resolveImageUrl(rawUrl) : undefined;
  const showPlaceholderImage = hasError && hasRealUrl && !rawUrl?.startsWith("blob:");

  if (resolvedUrl && !showPlaceholderImage) {
    return (
      <div className={`relative overflow-hidden bg-zinc-100 ${className}`}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={resolvedUrl}
          alt={image.alt || "Product image"}
          className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
          onError={() => setHasError(true)}
        />
      </div>
    );
  }

  if (hasError || showPlaceholderImage) {
    return (
      <div className={`relative overflow-hidden bg-zinc-100 ${className}`}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={PRODUCT_PLACEHOLDER_IMAGE}
          alt={image.alt || "Product placeholder"}
          className="h-full w-full object-contain p-6 opacity-80"
        />
      </div>
    );
  }

  return (
    <div
      className={`flex items-center justify-center bg-gradient-to-br ${gradient} ${className}`}
    >
      <span
        className={`drop-shadow-md transition duration-500 group-hover:scale-110 ${emojiClassName}`}
      >
        {emoji}
      </span>
    </div>
  );
}
