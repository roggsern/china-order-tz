import type { Product, ProductImage } from "@/lib/types/catalog";
import { getAppUrl, getPublicApiUrl, isDevelopment } from "@/lib/config/env";

export const PRODUCT_PLACEHOLDER_IMAGE = "/images/product-placeholder.svg";

type ProductImageCarrier = Pick<
  Product,
  "primary_image" | "images" | "image" | "name" | "thumbnailImageId" | "emoji" | "gradient"
>;

type ImageLike = Partial<ProductImage> & {
  alt_text?: string | null;
};

function getImageBaseUrl(): string {
  if (typeof window !== "undefined") {
    return window.location.origin;
  }

  return getAppUrl() || (isDevelopment() ? "http://localhost:3000" : "");
}

function stripStoragePrefix(value: string): string {
  return value.replace(/^\/+/, "").replace(/^storage\//, "");
}

function buildLaravelStorageUrl(relativePath: string): string | null {
  const apiBase = getPublicApiUrl().replace(/\/$/, "");

  if (!apiBase) {
    if (isDevelopment()) {
      console.warn(
        "[resolveImageUrl] NEXT_PUBLIC_API_URL is not set. Cannot resolve Laravel storage path:",
        relativePath,
        "Run `php artisan storage:link` on the API and set NEXT_PUBLIC_API_URL.",
      );
    }

    return null;
  }

  return `${apiBase}/storage/${stripStoragePrefix(relativePath)}`;
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

  if (
    trimmed.startsWith("/storage/") ||
    trimmed.startsWith("storage/") ||
    trimmed.startsWith("products/") ||
    trimmed.startsWith("demo-products/")
  ) {
    const storageUrl = buildLaravelStorageUrl(trimmed);

    if (storageUrl) {
      return storageUrl;
    }

    return PRODUCT_PLACEHOLDER_IMAGE;
  }

  if (trimmed.startsWith("/")) {
    const base = getImageBaseUrl().replace(/\/$/, "");
    return `${base}${trimmed}`;
  }

  const storageUrl = buildLaravelStorageUrl(trimmed);

  return storageUrl ?? PRODUCT_PLACEHOLDER_IMAGE;
}

export function isPlaceholderImageUrl(url?: string | null): boolean {
  if (!url?.trim()) return false;
  const trimmed = url.trim();
  return trimmed === PRODUCT_PLACEHOLDER_IMAGE || trimmed.endsWith("/product-placeholder.svg");
}

function imageSourceCandidates(image: ImageLike | undefined): string[] {
  if (!image) {
    return [];
  }

  return [image.url?.trim(), image.path?.trim()].filter(
    (candidate): candidate is string => Boolean(candidate),
  );
}

function firstUsableImageSource(candidates: Array<string | undefined>): string | undefined {
  for (const candidate of candidates) {
    const trimmed = candidate?.trim();

    if (trimmed && !isPlaceholderImageUrl(trimmed)) {
      return trimmed;
    }
  }

  return undefined;
}

function normalizeImageRecord(
  image: ImageLike | undefined,
  product: ProductImageCarrier,
): ProductImage | undefined {
  const rawSrc = firstUsableImageSource(imageSourceCandidates(image));

  if (!rawSrc || !image) {
    return undefined;
  }

  const path = image.path?.trim();
  const alt = image.alt?.trim() || image.alt_text?.trim() || product.name;

  return {
    id: typeof image.id === "number" ? image.id : 0,
    emoji: image.emoji || product.emoji || "📦",
    gradient: image.gradient || product.gradient || "from-zinc-200 to-zinc-300",
    alt,
    url: rawSrc,
    path: path || undefined,
  };
}

function resolveGalleryImage(product: ProductImageCarrier): ProductImage | undefined {
  if (!product.images?.length) {
    return undefined;
  }

  if (product.thumbnailImageId != null) {
    const thumb = product.images.find((image) => image.id === product.thumbnailImageId);
    const normalizedThumb = normalizeImageRecord(thumb, product);

    if (normalizedThumb) {
      return normalizedThumb;
    }
  }

  for (const image of product.images) {
    const normalized = normalizeImageRecord(image, product);

    if (normalized) {
      return normalized;
    }
  }

  return undefined;
}

/** Resolve the first usable catalog image for a product. */
export function resolveCatalogProductImage(
  product: ProductImageCarrier,
): ProductImage | undefined {
  const fromPrimary = normalizeImageRecord(product.primary_image, product);

  if (fromPrimary) {
    return fromPrimary;
  }

  const fromGallery = resolveGalleryImage(product);

  if (fromGallery) {
    return fromGallery;
  }

  const legacyImage = product.image?.trim();

  if (legacyImage && !isPlaceholderImageUrl(legacyImage)) {
    return {
      id: 0,
      emoji: product.emoji || "📦",
      gradient: product.gradient || "from-zinc-200 to-zinc-300",
      alt: product.name,
      url: legacyImage,
    };
  }

  return undefined;
}

/** Resolve the first usable catalog image source for a product card. */
export function getCatalogProductImageSrc(product: ProductImageCarrier): string | undefined {
  return resolveCatalogProductImage(product)?.url;
}

export function getProductImageSource(product: ProductImageCarrier): string | undefined {
  return getCatalogProductImageSrc(product);
}

export function getProductPrimaryImage(product: ProductImageCarrier): ProductImage {
  const resolved = resolveCatalogProductImage(product);

  if (resolved) {
    return resolved;
  }

  return {
    id: 0,
    emoji: product.emoji || "📦",
    gradient: product.gradient || "from-zinc-200 to-zinc-300",
    alt: product.name,
  };
}

/**
 * Color-aware gallery resolution (presentation layer).
 * When images later include color metadata in alt/path/filename, selecting a color
 * prefers matching slides. If none match, the full gallery is returned unchanged.
 */
export function getProductGalleryImagesForColor(
  product: ProductImageCarrier,
  selectedColorSlug?: string | null,
): ProductImage[] {
  const images = getProductGalleryImages(product);
  const colorSlug = selectedColorSlug?.trim().toLowerCase();

  if (!colorSlug || images.length <= 1) {
    return images;
  }

  const matches = images.filter((image) => {
    const haystack = `${image.alt ?? ""} ${image.path ?? ""} ${image.url ?? ""}`.toLowerCase();
    return (
      haystack.includes(colorSlug) ||
      haystack.includes(colorSlug.replace(/-/g, " ")) ||
      haystack.includes(colorSlug.replace(/-/g, ""))
    );
  });

  return matches.length > 0 ? matches : images;
}

export function getProductGalleryImages(product: ProductImageCarrier): ProductImage[] {
  const gallery: ProductImage[] = [];

  const primary = normalizeImageRecord(product.primary_image, product);

  if (primary) {
    gallery.push(primary);
  }

  if (product.images?.length) {
    for (const [index, image] of product.images.entries()) {
      const normalized = normalizeImageRecord(image, product);

      if (!normalized) {
        continue;
      }

      const duplicate = gallery.some(
        (entry) =>
          entry.url === normalized.url ||
          (entry.path && normalized.path && entry.path === normalized.path),
      );

      if (!duplicate) {
        gallery.push({
          ...normalized,
          alt: normalized.alt || `${product.name} image ${index + 1}`,
        });
      }
    }
  }

  if (gallery.length > 0) {
    return gallery;
  }

  const legacyImage = product.image?.trim();

  if (legacyImage && !isPlaceholderImageUrl(legacyImage)) {
    return [
      {
        id: 0,
        emoji: product.emoji || "📦",
        gradient: product.gradient || "from-zinc-200 to-zinc-300",
        alt: product.name,
        url: legacyImage,
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
