"use client";

import { useCallback, useRef, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import type { Product, ProductImage } from "@/lib/types/catalog";
import { getProductGalleryImages } from "@/lib/catalog/product-images";
import { CloseIcon } from "@/components/home/icons";
import { ProductImageDisplay } from "../ProductImageDisplay";

interface ProductGalleryMobileProps {
  product: Pick<Product, "images" | "image" | "name" | "emoji" | "gradient">;
}

export function ProductGalleryMobile({ product }: ProductGalleryMobileProps) {
  const images = getProductGalleryImages(product);
  const reduceMotion = useReducedMotion();
  const scrollRef = useRef<HTMLDivElement>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const [zoomOpen, setZoomOpen] = useState(false);

  const syncIndexFromScroll = useCallback(() => {
    const container = scrollRef.current;
    if (!container || images.length === 0) return;

    const width = container.clientWidth;
    if (width <= 0) return;

    const index = Math.round(container.scrollLeft / width);
    setActiveIndex(Math.min(Math.max(index, 0), images.length - 1));
  }, [images.length]);

  const scrollToIndex = (index: number) => {
    const container = scrollRef.current;
    if (!container) return;

    container.scrollTo({
      left: index * container.clientWidth,
      behavior: reduceMotion ? "auto" : "smooth",
    });
    setActiveIndex(index);
  };

  if (images.length === 0) return null;

  const activeImage = images[activeIndex] ?? images[0];

  return (
    <>
      <div className="relative bg-zinc-50">
        <div
          ref={scrollRef}
          onScroll={syncIndexFromScroll}
          className="flex snap-x snap-mandatory overflow-x-auto scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {images.map((image, index) => (
            <button
              key={image.id}
              type="button"
              onClick={() => {
                setActiveIndex(index);
                setZoomOpen(true);
              }}
              className="relative w-full shrink-0 snap-center"
              aria-label={`View ${product.name} image ${index + 1}`}
            >
              <ProductImageDisplay
                image={image}
                fallbackEmoji={product.emoji}
                fallbackGradient={product.gradient}
                className="aspect-square w-full"
                emojiClassName="text-7xl drop-shadow-lg"
              />
            </button>
          ))}
        </div>

        {images.length > 1 && (
          <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
            {images.map((image, index) => (
              <span
                key={image.id}
                className={`h-1.5 rounded-full transition-all duration-300 ${
                  index === activeIndex ? "w-5 bg-[#c9a227]" : "w-1.5 bg-white/70"
                }`}
              />
            ))}
          </div>
        )}
      </div>

      {images.length > 1 && (
        <div className="flex gap-2 overflow-x-auto px-4 py-3 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          {images.map((image, index) => (
            <button
              key={image.id}
              type="button"
              onClick={() => scrollToIndex(index)}
              className={`h-16 w-16 shrink-0 overflow-hidden rounded-xl ring-2 transition ${
                index === activeIndex
                  ? "ring-[#c9a227] ring-offset-1"
                  : "ring-transparent opacity-70"
              }`}
              aria-label={`Select image ${index + 1}`}
              aria-current={index === activeIndex}
            >
              <ProductImageDisplay
                image={image}
                fallbackEmoji={product.emoji}
                fallbackGradient={product.gradient}
                className="h-full w-full"
                emojiClassName="text-xl"
              />
            </button>
          ))}
        </div>
      )}

      <ImageZoomModal
        image={activeImage}
        product={product}
        open={zoomOpen}
        onClose={() => setZoomOpen(false)}
      />
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
