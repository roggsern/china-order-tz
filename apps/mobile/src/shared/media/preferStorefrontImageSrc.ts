/**
 * Prefer storefront display derivative, then original url/path.
 * Single choke-point for mobile catalog/PDP/cart/search/homepage image selection.
 *
 * Does not invent URLs, rewrite hosts, or absolutize storage paths.
 */
export type StorefrontImageSource = {
  display_url?: string | null;
  original_url?: string | null;
  url?: string | null;
  path?: string | null;
} | null | undefined;

function firstNonEmpty(...candidates: Array<string | null | undefined>): string | null {
  for (const candidate of candidates) {
    const trimmed = candidate?.trim();
    if (trimmed) {
      return trimmed;
    }
  }
  return null;
}

/**
 * Customer-facing display source:
 * display_url → url → path → null
 *
 * `original_url` is intentionally ignored for normal display (archive/zoom later).
 */
export function preferStorefrontImageSrc(image: StorefrontImageSource): string | null {
  if (!image) {
    return null;
  }

  return firstNonEmpty(image.display_url, image.url, image.path);
}

/**
 * Accept unknown API media blobs (Zod passthrough / untyped mappers).
 */
export function preferStorefrontImageSrcFromUnknown(media: unknown): string | null {
  if (!media || typeof media !== 'object') {
    return null;
  }

  const record = media as Record<string, unknown>;
  const asString = (key: string): string | null => {
    const value = record[key];
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
  };

  return preferStorefrontImageSrc({
    display_url: asString('display_url'),
    original_url: asString('original_url'),
    url: asString('url'),
    path: asString('path'),
  });
}
