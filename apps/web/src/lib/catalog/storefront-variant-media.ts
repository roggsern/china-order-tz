import type { Product, ProductImage } from "@/lib/types/catalog";

export type CartLineImageSource = {
  product?: {
    primary_image?: {
      id?: string;
      url?: string | null;
      path?: string | null;
      alt_text?: string | null;
      alt?: string | null;
    } | null;
    images?: Array<{
      id?: string;
      url?: string | null;
      path?: string | null;
      is_primary?: boolean;
      alt_text?: string | null;
      alt?: string | null;
    }> | null;
    name?: string;
  } | null;
  variant?: {
    primary_image?: {
      id?: string;
      url?: string | null;
      path?: string | null;
      alt_text?: string | null;
    } | null;
    images?: Array<{
      id?: string;
      url?: string | null;
      path?: string | null;
      alt_text?: string | null;
    }> | null;
  } | null;
};

function firstUsableImage(
  candidates: Array<{
    id?: string;
    url?: string | null;
    path?: string | null;
    alt_text?: string | null;
    alt?: string | null;
    is_primary?: boolean;
  } | null | undefined>,
) {
  for (const candidate of candidates) {
    if (!candidate) continue;
    const src = candidate.url?.trim() || candidate.path?.trim();
    if (!src) continue;
    return candidate;
  }
  return null;
}

/** Prefer variant media, then product primary/gallery — for cart & checkout display. */
export function resolveCartLineDisplayImage(
  item: CartLineImageSource,
  fallbackId: string,
): {
  id: string;
  emoji: string;
  gradient: string;
  alt: string;
  url?: string;
  path?: string;
} {
  const variantImages = item.variant?.images ?? [];
  const productImages = item.product?.images ?? [];
  const primary =
    firstUsableImage([
      item.variant?.primary_image,
      variantImages.find((image) => image?.url || image?.path),
      item.product?.primary_image,
      productImages.find((image) => image?.is_primary),
      productImages[0],
    ]) ?? null;

  return {
    id: primary?.id ?? fallbackId,
    emoji: "🛒",
    gradient: "from-zinc-100 to-zinc-200",
    alt: primary?.alt_text?.trim() || primary?.alt?.trim() || item.product?.name || "Product",
    url: primary?.url ?? undefined,
    path: primary?.path ?? undefined,
  };
}

export function resolveVariantCartImage(
  product: Pick<Product, "images" | "primary_image" | "variantGalleries" | "emoji" | "gradient" | "name">,
  configurationId?: string | null,
): ProductImage {
  const variantId = configurationId?.trim() || null;
  if (variantId) {
    const gallery = product.variantGalleries?.[variantId];
    if (gallery && gallery.length > 0) {
      return gallery[0];
    }
  }

  return (
    product.primary_image ??
    product.images[0] ?? {
      id: 0,
      emoji: product.emoji || "📦",
      gradient: product.gradient || "from-zinc-200 to-zinc-300",
      alt: product.name,
    }
  );
}
