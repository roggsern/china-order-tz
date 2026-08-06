"use client";

import { useCallback, useEffect, useRef, useState, type MouseEvent } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { Product, ProductImage } from "@/lib/types/catalog";
import { getProductGalleryMedia, type ProductGalleryMediaSlide } from "@/lib/catalog/product-gallery-media";
import { ProductImageDisplay } from "./ProductImageDisplay";
import { ProductVideoDisplay } from "./ProductVideoDisplay";

interface ProductGalleryProps {
  product: Pick<
    Product,
    | "primary_image"
    | "images"
    | "image"
    | "name"
    | "emoji"
    | "gradient"
    | "videos"
    | "variantGalleries"
  >;
  /** @deprecated Pass `product` instead */
  images?: ProductImage[];
  productName?: string;
  fallbackEmoji?: string;
  fallbackGradient?: string;
  /** Prefer gallery slides that match this color slug when color-tagged images exist. */
  selectedColorSlug?: string | null;
  /** Prefer variant-bound gallery when the customer has matched a configuration. */
  configurationId?: string | null;
  /** Gallery-only preview from partial Color/visual selection. */
  mediaPreviewConfigurationId?: string | null;
}

function renderGallerySlide(
  slide: ProductGalleryMediaSlide,
  options: {
    emoji: string;
    gradient: string;
    thumbnailOnly?: boolean;
  },
) {
  if (slide.kind === "video") {
    return (
      <ProductVideoDisplay
        video={slide.video}
        className="h-full w-full"
        thumbnailOnly={options.thumbnailOnly}
      />
    );
  }

  return (
    <ProductImageDisplay
      image={slide.image}
      fallbackEmoji={options.emoji}
      fallbackGradient={options.gradient}
      className="h-full w-full"
      emojiClassName={options.thumbnailOnly ? "text-xl" : "text-8xl drop-shadow-lg sm:text-9xl"}
    />
  );
}

export function ProductGallery({
  product,
  images: legacyImages,
  productName,
  fallbackEmoji,
  fallbackGradient,
  selectedColorSlug = null,
  configurationId = null,
  mediaPreviewConfigurationId = null,
}: ProductGalleryProps) {
  const galleryProduct = legacyImages?.length
    ? {
        ...product,
        images: legacyImages,
        name: productName ?? product.name,
      }
    : product;

  const media = getProductGalleryMedia(
    galleryProduct,
    selectedColorSlug,
    configurationId,
    mediaPreviewConfigurationId,
  );
  const name = productName ?? product.name;
  const emoji = fallbackEmoji ?? product.emoji;
  const gradient = fallbackGradient ?? product.gradient;
  const reduceMotion = useReducedMotion();

  const [activeIndex, setActiveIndex] = useState(0);
  const [isZooming, setIsZooming] = useState(false);
  const [zoomPosition, setZoomPosition] = useState({ x: 50, y: 50 });
  const mainRef = useRef<HTMLDivElement>(null);
  const touchStartX = useRef<number | null>(null);

  useEffect(() => {
    setActiveIndex(0);
  }, [selectedColorSlug, configurationId, mediaPreviewConfigurationId, media.length]);

  const activeSlide = media[activeIndex] ?? media[0];
  const activeIsImage = activeSlide?.kind === "image";

  const handleMouseMove = useCallback(
    (event: MouseEvent<HTMLDivElement>) => {
      if (!activeIsImage) {
        return;
      }

      const container = mainRef.current;
      if (!container) return;

      const rect = container.getBoundingClientRect();
      const x = ((event.clientX - rect.left) / rect.width) * 100;
      const y = ((event.clientY - rect.top) / rect.height) * 100;
      setZoomPosition({
        x: Math.min(Math.max(x, 0), 100),
        y: Math.min(Math.max(y, 0), 100),
      });
    },
    [activeIsImage],
  );

  const goToIndex = (index: number) => {
    setActiveIndex(Math.min(Math.max(index, 0), media.length - 1));
  };

  const handleTouchStart = (event: React.TouchEvent) => {
    touchStartX.current = event.touches[0]?.clientX ?? null;
  };

  const handleTouchEnd = (event: React.TouchEvent) => {
    if (touchStartX.current === null || media.length <= 1) return;

    const endX = event.changedTouches[0]?.clientX ?? touchStartX.current;
    const delta = endX - touchStartX.current;
    touchStartX.current = null;

    if (Math.abs(delta) < 40) return;
    if (delta < 0) goToIndex(activeIndex + 1);
    else goToIndex(activeIndex - 1);
  };

  if (!activeSlide) return null;

  return (
    <div className="flex w-full gap-3 sm:gap-4 lg:w-full lg:gap-3.5">
      {media.length > 1 && (
        <div className="hidden w-[4.75rem] shrink-0 flex-col gap-2.5 sm:flex lg:w-[3.5rem] lg:gap-2">
          {media.map((slide, index) => {
            const isActive = index === activeIndex;
            return (
              <button
                key={slide.key}
                type="button"
                onClick={() => goToIndex(index)}
                onMouseEnter={() => {
                  if (!reduceMotion) goToIndex(index);
                }}
                className={`relative aspect-square w-full overflow-hidden rounded-2xl transition-all duration-300 ${
                  isActive
                    ? "scale-[1.03] shadow-[0_6px_20px_rgba(201,162,39,0.25)] ring-2 ring-[#c9a227] ring-offset-2"
                    : "opacity-65 ring-1 ring-zinc-200/80 hover:opacity-100 hover:ring-[#c9a227]/40"
                }`}
                aria-label={`View ${name} slide ${index + 1}`}
                aria-current={isActive}
              >
                {renderGallerySlide(slide, { emoji, gradient, thumbnailOnly: slide.kind === "video" })}
              </button>
            );
          })}
        </div>
      )}

      <div className="min-w-0 w-full flex-1">
        <div
          ref={mainRef}
          className={`group relative w-full overflow-hidden rounded-[1.75rem] bg-zinc-50 shadow-[0_12px_40px_rgba(0,0,0,0.08)] ring-1 ring-zinc-200/70 ${
            activeIsImage ? "cursor-zoom-in" : "cursor-default"
          }`}
          onMouseEnter={() => {
            if (activeIsImage) {
              setIsZooming(true);
            }
          }}
          onMouseLeave={() => setIsZooming(false)}
          onMouseMove={handleMouseMove}
          onTouchStart={handleTouchStart}
          onTouchEnd={handleTouchEnd}
        >
          <AnimatePresence mode="wait">
            <motion.div
              key={`${configurationId ?? mediaPreviewConfigurationId ?? selectedColorSlug ?? "all"}-${activeSlide.key}`}
              initial={reduceMotion ? false : { opacity: 0, scale: 1.02 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={reduceMotion ? undefined : { opacity: 0, scale: 0.985 }}
              transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1] }}
              className="relative aspect-square w-full overflow-hidden sm:aspect-[4/5] lg:aspect-square"
            >
              {activeIsImage ? (
                <div
                  className="h-full w-full will-change-transform"
                  style={{
                    transform: isZooming && !reduceMotion ? "scale(1.55)" : "scale(1)",
                    transformOrigin: `${zoomPosition.x}% ${zoomPosition.y}%`,
                    transition: reduceMotion
                      ? "none"
                      : "transform 220ms cubic-bezier(0.22, 1, 0.36, 1)",
                  }}
                >
                  {renderGallerySlide(activeSlide, { emoji, gradient })}
                </div>
              ) : (
                renderGallerySlide(activeSlide, { emoji, gradient })
              )}
            </motion.div>
          </AnimatePresence>

          <div className="pointer-events-none absolute inset-x-0 top-0 flex items-start justify-between p-3">
            {selectedColorSlug ? (
              <span className="rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold capitalize text-zinc-700 shadow-sm backdrop-blur-sm">
                {selectedColorSlug.replace(/-/g, " ")} view
              </span>
            ) : (
              <span />
            )}
            <span className="rounded-full bg-black/45 px-2.5 py-1 text-[11px] font-medium text-white opacity-100 backdrop-blur-sm sm:opacity-0 sm:transition sm:group-hover:opacity-100">
              {media.length > 1
                ? `${activeIndex + 1} of ${media.length}`
                : activeIsImage
                  ? "Hover to zoom"
                  : "Product video"}
            </span>
          </div>

          {media.length > 1 && (
            <>
              <button
                type="button"
                onClick={() => goToIndex(activeIndex - 1)}
                className="absolute left-3 top-1/2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-zinc-800 opacity-0 shadow-md backdrop-blur-sm transition hover:bg-white group-hover:opacity-100"
                aria-label="Previous slide"
              >
                ‹
              </button>
              <button
                type="button"
                onClick={() => goToIndex(activeIndex + 1)}
                className="absolute right-3 top-1/2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-zinc-800 opacity-0 shadow-md backdrop-blur-sm transition hover:bg-white group-hover:opacity-100"
                aria-label="Next slide"
              >
                ›
              </button>
              <span className="absolute bottom-3 right-3 rounded-full bg-black/55 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                {activeIndex + 1} / {media.length}
              </span>
            </>
          )}
        </div>

        {media.length > 1 && (
          <div className="mt-3 flex gap-2.5 overflow-x-auto pb-1 sm:hidden">
            {media.map((slide, index) => (
              <button
                key={slide.key}
                type="button"
                onClick={() => goToIndex(index)}
                className={`h-[4.25rem] w-[4.25rem] shrink-0 overflow-hidden rounded-2xl transition ${
                  index === activeIndex
                    ? "ring-2 ring-[#c9a227] ring-offset-1"
                    : "opacity-70 ring-1 ring-zinc-200"
                }`}
                aria-label={`Select slide ${index + 1}`}
              >
                {renderGallerySlide(slide, {
                  emoji,
                  gradient,
                  thumbnailOnly: slide.kind === "video",
                })}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
