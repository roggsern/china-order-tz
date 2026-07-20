/**
 * Legacy helpers for display labels. BUY FROM TZ navigation must use
 * `/buy-from-tz` + storefront stores API — never catalog brands.
 */

export interface BrandCategory {
  name: string;
  slug: string;
}

export interface BrandMenuItem {
  slug: string;
  name: string;
  icon: string;
  tagline: string;
  subcategories: readonly BrandCategory[];
}

/** Canonical TZ retail units (slugs must match StoreSeeder). */
export const buyFromTzBrandMenu: readonly BrandMenuItem[] = [
  {
    slug: "zion-mode",
    name: "ZION MODE",
    icon: "👗",
    tagline: "Premium women's fashion from Dar es Salaam",
    subcategories: [],
  },
  {
    slug: "peachy-lingerie",
    name: "PEACHY LINGERIE",
    icon: "🩱",
    tagline: "Elegant lingerie & intimate apparel",
    subcategories: [],
  },
  {
    slug: "tzur-jewelry",
    name: "TZUR JEWELRY",
    icon: "💎",
    tagline: "Fine jewelry & statement pieces",
    subcategories: [],
  },
  {
    slug: "rovi-beauty",
    name: "ROVI BEAUTY",
    icon: "💄",
    tagline: "Wigs, skincare & beauty essentials",
    subcategories: [],
  },
] as const;

export function formatBrandDisplayName(name: string): string {
  return name
    .split(" ")
    .map((word) => word.charAt(0) + word.slice(1).toLowerCase())
    .join(" ");
}

export const buyFromTzBrands = buyFromTzBrandMenu.map((brand) => ({
  label: formatBrandDisplayName(brand.name),
  slug: brand.slug,
}));

export function getBrandCategoryHref(brandSlug: string, categorySlug: string): string {
  return `/buy-from-tz/${brandSlug}/category/${categorySlug}`;
}

export function getBrandBySlug(slug: string): BrandMenuItem | undefined {
  return buyFromTzBrandMenu.find((brand) => brand.slug === slug);
}

export function getBrandDisplayLabel(slug: string): string | undefined {
  const brand = getBrandBySlug(slug);
  return brand ? formatBrandDisplayName(brand.name) : undefined;
}

export function getBrandSubcategories(brandSlug: string): readonly BrandCategory[] {
  return getBrandBySlug(brandSlug)?.subcategories ?? [];
}

export function isValidBrandSubcategory(brandSlug: string, subcategorySlug: string): boolean {
  return getBrandSubcategories(brandSlug).some((item) => item.slug === subcategorySlug);
}

export function getDefaultBuyFromDarBrand(): BrandMenuItem {
  return buyFromTzBrandMenu[0];
}

export function getBrandCategory(
  brandSlug: string,
  categorySlug: string,
): BrandCategory | undefined {
  const brand = getBrandBySlug(brandSlug);
  return brand?.subcategories.find((category) => category.slug === categorySlug);
}
