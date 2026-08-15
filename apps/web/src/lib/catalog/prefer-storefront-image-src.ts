/**
 * Prefer storefront display derivative, then original url/path.
 * Single choke-point for PDP/gallery/card/warm URL selection.
 */
export function preferStorefrontImageSrc(image: {
  display_url?: string | null;
  url?: string | null;
  path?: string | null;
} | null | undefined): string | null {
  if (!image) {
    return null;
  }

  const display = image.display_url?.trim();
  if (display) {
    return display;
  }

  const url = image.url?.trim();
  if (url) {
    return url;
  }

  const path = image.path?.trim();
  if (path) {
    return path;
  }

  return null;
}
