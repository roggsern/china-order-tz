"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { Product, ProductImage } from "@/lib/types/catalog";
import { getProductGalleryMedia, type ProductGalleryMediaSlide } from "@/lib/catalog/product-gallery-media";
import { CloseIcon } from "@/components/home/icons";
import { ProductImageDisplay } from "../ProductImageDisplay";
import { ProductVideoDisplay } from "../ProductVideoDisplay";

interface ProductGalleryMobileProps {
  product: Pick<
    Product,
    | "images"
    | "image"
    | "name"
    | "emoji"
    | "gradient"
    | "primary_image"
    | "videos"
    | "variantGalleries"
  >;
  selectedColorSlug?: string | null;
  configurationId?: string | null;
}

function renderGallerySlide(
  slide: ProductGalleryMediaSlide,
  product: Pick<Product, "emoji" | "gradient">,
  options?: { thumbnailOnly?: boolean },
) {
  if (slide.kind === "video") {
    return (
      <ProductVideoDisplay
        video={slide.video}
        className="h-full w-full"
        thumbnailOnly={options?.thumbnailOnly}
      />
    );
  }

  return (
    <ProductImageDisplay
      image={slide.image}
      fallbackEmoji={product.emoji}
      fallbackGradient={product.gradient}
      className="h-full w-full"
      emojiClassName={options?.thumbnailOnly ? "text-xl" : "text-7xl drop-shadow-lg"}
    />
  );
}

export function ProductGalleryMobile({
  product,
  selectedColorSlug = null,
  configurationId = null,
}: ProductGalleryMobileProps) {
  const media = getProductGalleryMedia(product, selectedColorSlug, configurationId);
  const reduceMotion = useReducedMotion();
  const scrollRef = useRef<HTMLDivElement>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const [zoomOpen, setZoomOpen] = useState(false);

  const syncIndexFromScroll = useCallback(() => {
    const container = scrollRef.current;
    if (!container || media.length === 0) return;

    const width = container.clientWidth;
    if (width <= 0) return;

    const index = Math.round(container.scrollLeft / width);
    setActiveIndex(Math.min(Math.max(index, 0), media.length - 1));
  }, [media.length]);

  const scrollToIndex = (index: number) => {
    const container = scrollRef.current;
    if (!container) return;

    container.scrollTo({
      left: index * container.clientWidth,
      behavior: reduceMotion ? "auto" : "smooth",
    });
    setActiveIndex(index);
  };

  useEffect(() => {
    setActiveIndex(0);
    scrollRef.current?.scrollTo({ left: 0, behavior: "auto" });
  }, [selectedColorSlug, configurationId, media.length]);

  if (media.length === 0) return null;

  const activeSlide = media[activeIndex] ?? media[0];
  const activeImage = activeSlide.kind === "image" ? activeSlide.image : null;

  return (
    <>
      <div className="relative overflow-hidden bg-gradient-to-b from-zinc-50 to-white">
        {selectedColorSlug ? (
          <span className="absolute left-3 top-3 z-10 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold capitalize text-zinc-700 shadow-sm backdrop-blur-sm">
            {selectedColorSlug.replace(/-/g, " ")} view
          </span>
        ) : null}
        <div
          ref={scrollRef}
          onScroll={syncIndexFromScroll}
          className="flex snap-x snap-mandatory overflow-x-auto scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {media.map((slide, index) => (
            <motion.div
              key={slide.key}
              className="relative w-full shrink-0 snap-center"
              initial={false}
              animate={{ opacity: activeIndex === index ? 1 : 0.92 }}
              transition={{ duration: 0.2 }}
            >
              {slide.kind === "image" ? (
                <button
                  type="button"
                  onClick={() => {
                    setActiveIndex(index);
                    setZoomOpen(true);
                  }}
                  className="block w-full"
                  aria-label={`View ${product.name} image ${index + 1}`}
                >
                  {renderGallerySlide(slide, product)}
                </button>
              ) : (
                <div className="aspect-square w-full" aria-label={`${product.name} product video`}>
                  {renderGallerySlide(slide, product)}
                </div>
              )}
            </motion.div>
          ))}
        </div>

        {media.length > 1 && (
          <>
            <span className="absolute bottom-3 right-3 rounded-full bg-black/55 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm">
              {activeIndex + 1} / {media.length}
            </span>
            <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
              {media.map((slide, index) => (
                <motion.span
                  key={slide.key}
                  layout
                  className={`h-1.5 rounded-full ${
                    index === activeIndex ? "bg-[#c9a227]" : "bg-white/70"
                  }`}
                  animate={{ width: index === activeIndex ? 20 : 6 }}
                  transition={{ type: "spring", stiffness: 400, damping: 28 }}
                />
              ))}
            </div>
          </>
        )}
      </div>

      {media.length > 1 && (
        <div className="flex gap-2 overflow-x-auto px-4 py-3 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          {media.map((slide, index) => (
            <button
              key={slide.key}
              type="button"
              onClick={() => scrollToIndex(index)}
              className={`h-16 w-16 shrink-0 overflow-hidden rounded-xl ring-2 transition ${
                index === activeIndex
                  ? "ring-[#c9a227] ring-offset-1"
                  : "ring-transparent opacity-70"
              }`}
              aria-label={`Select slide ${index + 1}`}
              aria-current={index === activeIndex}
            >
              {renderGallerySlide(slide, product, { thumbnailOnly: slide.kind === "video" })}
            </button>
          ))}
        </div>
      )}

      {activeImage ? (
        <ImageZoomModal
          image={activeImage}
          product={product}
          open={zoomOpen}
          onClose={() => setZoomOpen(false)}
        />
      ) : null}
    </>
  );
}

function ImageZoomModal({
  image,
  product,
  open,
  onClose,
}: {
  image: ProductImage;
  product: Pick<Product, "name" | "emoji" | "gradient">;
  open: boolean;
  onClose: () => void;
}) {
  return (
    <AnimatePresence>
      {open && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.2 }}
          className="fixed inset-0 z-[70] flex items-center justify-center bg-black/90 p-4"
          role="dialog"
          aria-modal="true"
          aria-label="Product image zoom"
          onClick={onClose}
        >
          <button
            type="button"
            onClick={onClose}
            className="absolute right-4 top-4 z-10 rounded-full bg-white/10 p-2 text-white backdrop-blur-sm"
            aria-label="Close zoom"
          >
            <CloseIcon className="h-5 w-5" />
          </button>

          <motion.div
            initial={{ scale: 0.92, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0.92, opacity: 0 }}
            transition={{ type: "spring", stiffness: 320, damping: 28 }}
            className="w-full max-w-lg overflow-hidden rounded-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <ProductImageDisplay
              image={image}
              fallbackEmoji={product.emoji}
              fallbackGradient={product.gradient}
              className="aspect-square w-full"
              emojiClassName="text-8xl drop-shadow-lg"
            />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
