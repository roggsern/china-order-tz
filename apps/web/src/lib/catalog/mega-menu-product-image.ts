/**
 * Mega-menu featured tile image resolution.
 * Accepts the Wave 2 slim product shape (and full ApiCatalogProductCard structurally).
 */
export type MegaMenuProductImageSource = {
  primary_image?: {
    url?: string | null;
    path?: string | null;
  } | null;
};

export function resolveMegaMenuProductImage(
  product: MegaMenuProductImageSource,
): string | null {
  return product.primary_image?.url || product.primary_image?.path || null;
}
