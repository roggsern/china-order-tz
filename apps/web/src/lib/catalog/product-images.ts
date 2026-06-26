import type { Product, ProductImage } from "@/lib/types/catalog";

export const PRODUCT_PLACEHOLDER_IMAGE = "/images/product-placeholder.svg";

function getImageBaseUrl(): string {
  if (typeof window !== "undefined") {
    return window.location.origin;
  }

  return (
    process.env.NEXT_PUBLIC_API_URL ??
    process.env.API_URL ??
    process.env.NEXT_PUBLIC_APP_URL ??
    "http://localhost:3000"
  );
}

/** Resolve a stored image path to a browser-loadable URL. */
export function resolveImageUrl(src?: string | null): string {
  if (!src?.trim()) return PRODUCT_PLACEHOLDER_IMAGE;

  const trimmed = src.trim();

  if (trimmed.startsWith("blob:") || trimmed.startsWith("data:")) {
    return trimmed;
  }

  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    return trimmed;
  }

  if (trimmed.startsWith("/uploads") || trimmed.startsWith("/images")) {
    const base = getImageBaseUrl().replace(/\/$/, "");
    return `${base}${trimmed}`;
  }

  if (trimmed.startsWith("/")) {
    return trimmed;
  }

  return trimmed;
}

export function isPlaceholderImageUrl(url?: string | null): boolean {
  if (!url?.trim()) return false;
  const trimmed = url.trim();
  return trimmed === PRODUCT_PLACEHOLDER_IMAGE || trimmed.endsWith("/product-placeholder.svg");
}

function resolveGalleryImage(
  product: Pick<Product, "images" | "image" | "name" | "thumbnailImageId">,
): ProductImage | undefined {
  if (!product.images?.length) {
    return undefined;
  }

  if (product.thumbnailImageId != null) {
    const thumb = product.images.find((image) => image.id === product.thumbnailImageId);
    if (thumb) {
      return thumb;
    }
  }

  return product.images[0];
}

export function getProductImageSource(
  product: Pick<Product, "images" | "image" | "name" | "thumbnailImageId">,
): string | undefined {
  const galleryImage = resolveGalleryImage(product);
  const fromGallery = galleryImage?.url?.trim();
  if (fromGallery && !isPlaceholderImageUrl(fromGallery)) {
    return fromGallery;
  }

  const legacy = product.image?.trim();
  if (legacy && !isPlaceholderImageUrl(legacy)) {
    return legacy;
  }

  return undefined;
}

export function getProductPrimaryImage(
  product: Pick<Product, "images" | "image" | "name" | "emoji" | "gradient" | "thumbnailImageId">,
): ProductImage {
  const url = getProductImageSource(product);
  const galleryImage = resolveGalleryImage(product);

  if (galleryImage) {
    return {
      ...galleryImage,
      url: url || (galleryImage.url && !isPlaceholderImageUrl(galleryImage.url) ? galleryImage.url : undefined),
      alt: galleryImage.alt || product.name,
      emoji: galleryImage.emoji || product.emoji,
      gradient: galleryImage.gradient || product.gradient,
    };
  }

  if (url) {
    return {
      id: 0,
      emoji: product.emoji,
      gradient: product.gradient,
      alt: product.name,
      url,
    };
  }

  return {
    id: 0,
    emoji: product.emoji,
    gradient: product.gradient,
    alt: product.name,
  };
}

export function getProductGalleryImages(
  product: Pick<Product, "images" | "image" | "name" | "emoji" | "gradient" | "thumbnailImageId">,
): ProductImage[] {
  if (product.images?.length) {
    return product.images.map((image, index) => ({
      ...image,
      alt: image.alt || `${product.name} image ${index + 1}`,
      emoji: image.emoji || product.emoji,
      gradient: image.gradient || product.gradient,
    }));
  }

  const url = product.image?.trim();
  if (url && !isPlaceholderImageUrl(url)) {
    return [
      {
        id: 0,
        emoji: product.emoji,
        gradient: product.gradient,
        alt: product.name,
        url,
      },
    ];
  }

  return [getProductPrimaryImage(product)];
}

/** Normalize image paths before persisting to storage. */
export function normalizeStoredImagePath(url?: string | null): string | undefined {
  if (!url?.trim()) return undefined;

  const trimmed = url.trim();

  if (trimmed.startsWith("blob:")) return undefined;

  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    try {
      const parsed = new URL(trimmed);
      if (
        parsed.pathname.startsWith("/uploads") ||
        parsed.pathname.startsWith("/images")
      ) {
        return parsed.pathname;
      }
    } catch {
      return trimmed;
    }
    return trimmed;
  }

  if (trimmed.startsWith("/uploads") || trimmed.startsWith("/images")) {
    return trimmed;
  }

  return trimmed.startsWith("/") ? trimmed : `/${trimmed}`;
}

export function normalizeProductImagesForSave(images: ProductImage[]): ProductImage[] {
  return images.map((image) => {
    const url = normalizeStoredImagePath(image.url);
    return url ? { ...image, url } : { ...image, url: undefined };
  });
}

export function hasPersistableImageUrl(url?: string | null): boolean {
  if (!url?.trim()) return false;
  return !url.trim().startsWith("blob:");
}
