"use client";

import { useLayoutEffect, useRef, useState } from "react";
import type { Product, ProductImage } from "@/lib/types/catalog";
import {
  PRODUCT_PLACEHOLDER_IMAGE,
  getProductPrimaryImage,
  isPlaceholderImageUrl,
  resolveImageUrl,
} from "@/lib/catalog/product-images";

type ProductImageSource = Pick<
  Product,
  "primary_image" | "images" | "image" | "name" | "emoji" | "gradient" | "thumbnailImageId"
>;

interface ProductImageDisplayProps {
  /** Resolved image source from the server mapper (hydration-safe). */
  src?: string | null;
  /** Pre-resolved image (legacy callers). */
  image?: ProductImage;
  /** Preferred source — resolves on the client from API-mapped catalog fields. */
  product?: ProductImageSource;
  fallbackEmoji?: string;
  fallbackGradient?: string;
  className?: string;
  emojiClassName?: string;
}

function resolveDisplayImage(
  product: ProductImageSource | undefined,
  image: ProductImage | undefined,
): ProductImage {
  if (product) {
    return getProductPrimaryImage(product);
  }

  if (image) {
    const rawSrc = image.url?.trim() || image.path?.trim();

    if (rawSrc && !isPlaceholderImageUrl(rawSrc)) {
      return {
        ...image,
        url: rawSrc,
      };
    }

    return image;
  }

  return {
    id: 0,
    emoji: "📦",
    gradient: "from-zinc-200 to-zinc-300",
    alt: "Product",
  };
}

export function ProductImageDisplay({
  src,
  image,
  product,
  fallbackEmoji,
  fallbackGradient,
  className = "",
  emojiClassName = "text-6xl",
}: ProductImageDisplayProps) {
  const displayImage = resolveDisplayImage(product, image);
  const emoji = displayImage.emoji || fallbackEmoji || "📦";
  const gradient = displayImage.gradient || fallbackGradient || "from-zinc-200 to-zinc-300";
  const [hasError, setHasError] = useState(false);
  const [isLoaded, setIsLoaded] = useState(false);
  /** Last successfully shown URL — kept visible while a new cold asset loads. */
  const [visibleUrl, setVisibleUrl] = useState<string | undefined>(undefined);
  const imageRef = useRef<HTMLImageElement>(null);

  const rawUrl = src?.trim() || displayImage.url?.trim() || displayImage.path?.trim();
  const hasRealUrl = Boolean(rawUrl && !isPlaceholderImageUrl(rawUrl));
  const resolvedUrl = hasRealUrl && !hasError ? resolveImageUrl(rawUrl) : undefined;
  const showPlaceholderImage = hasError && hasRealUrl && !rawUrl?.startsWith("blob:");
  const pendingUrl =
    resolvedUrl && visibleUrl && resolvedUrl !== visibleUrl ? resolvedUrl : undefined;

  useLayoutEffect(() => {
    if (!resolvedUrl) {
      setVisibleUrl(undefined);
      setIsLoaded(false);
      setHasError(false);
      return;
    }

    setHasError(false);

    if (!visibleUrl) {
      setVisibleUrl(resolvedUrl);
      setIsLoaded(false);
    }
  }, [resolvedUrl, visibleUrl]);

  useLayoutEffect(() => {
    const img = imageRef.current;
    if (img?.complete && img.naturalWidth > 0) {
      setIsLoaded(true);
    }
  }, [visibleUrl]);

  const promoteUrl = (url: string) => {
    setVisibleUrl(url);
    setIsLoaded(true);
  };

  if ((resolvedUrl || visibleUrl) && !showPlaceholderImage) {
    return (
      <div className={`relative overflow-hidden bg-zinc-100 ${className}`}>
        <div
          className={`pointer-events-none absolute inset-0 z-[1] skeleton-shimmer transition-opacity duration-300 ${
            isLoaded ? "opacity-0" : "opacity-100"
          }`}
          aria-hidden="true"
        />
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          ref={imageRef}
          src={visibleUrl ?? resolvedUrl}
          alt={displayImage.alt || "Product image"}
          className={`relative z-[2] h-full w-full object-cover transition-all duration-500 ease-out group-hover:scale-[1.04] ${
            isLoaded || Boolean(visibleUrl) ? "opacity-100" : "opacity-0"
          }`}
          onLoad={() => setIsLoaded(true)}
          onError={() => setHasError(true)}
        />
        {pendingUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={pendingUrl}
            alt=""
            aria-hidden
            className="pointer-events-none absolute h-0 w-0 opacity-0"
            onLoad={() => promoteUrl(pendingUrl)}
            onError={() => {
              setVisibleUrl(pendingUrl);
              setHasError(true);
            }}
          />
        ) : null}
      </div>
    );
  }

  if (hasError || showPlaceholderImage) {
    return (
      <div className={`relative overflow-hidden bg-zinc-100 ${className}`}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={PRODUCT_PLACEHOLDER_IMAGE}
          alt={displayImage.alt || "Product placeholder"}
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
