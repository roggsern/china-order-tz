export interface BrandCategory {
  name: string;
  slug: string;
}

export interface BrandMenuItem {
  slug: string;
  name: string;
  icon: string;
  subcategories: readonly BrandCategory[];
}

export const buyFromTzBrandMenu: readonly BrandMenuItem[] = [
  {
    slug: "zion-mode",
    name: "ZION MODE",
    icon: "👗",
    subcategories: [
      { name: "Dresses", slug: "dresses" },
      { name: "Tops & Blouses", slug: "tops-blouses" },
      { name: "T-Shirts", slug: "t-shirts" },
      { name: "Jeans", slug: "jeans" },
      { name: "Trousers", slug: "trousers" },
      { name: "Skirts", slug: "skirts" },
      { name: "Jackets", slug: "jackets" },
      { name: "Hoodies", slug: "hoodies" },
      { name: "Sweaters", slug: "sweaters" },
      { name: "Two Piece Sets", slug: "two-piece-sets" },
      { name: "Activewear", slug: "activewear" },
      { name: "Shoes", slug: "shoes" },
      { name: "Handbags", slug: "handbags" },
      { name: "Accessories", slug: "accessories" },
      { name: "Beauty & Skin Care", slug: "beauty-skin-care" },
    ],
  },
  {
    slug: "peachy-lingerie",
    name: "PEACHY LINGERIE",
    icon: "🩱",
    subcategories: [
      { name: "Underwear", slug: "underwear" },
      { name: "Bras", slug: "bras" },
      { name: "Lingerie Sets", slug: "lingerie-sets" },
    ],
  },
  {
    slug: "tzur-jewelry",
    name: "TZUR JEWELRY",
    icon: "💎",
    subcategories: [
      { name: "Necklaces", slug: "necklaces" },
      { name: "Rings", slug: "rings" },
      { name: "Earrings", slug: "earrings" },
      { name: "Bracelets", slug: "bracelets" },
      { name: "Bangles", slug: "bangles" },
      { name: "Anklets", slug: "anklets" },
      { name: "Pendants", slug: "pendants" },
      { name: "Chains", slug: "chains" },
      { name: "Watches", slug: "watches" },
      { name: "Jewelry Sets", slug: "jewelry-sets" },
      { name: "Men's Jewelry", slug: "mens-jewelry" },
      { name: "Women's Jewelry", slug: "womens-jewelry" },
      { name: "Gift Collection", slug: "gift-collection" },
    ],
  },
] as const;

export const buyFromTzBrands = buyFromTzBrandMenu.map((brand) => ({
  label: brand.name
    .split(" ")
    .map((word) => word.charAt(0) + word.slice(1).toLowerCase())
    .join(" "),
  slug: brand.slug,
}));

export function getBrandCategoryHref(brandSlug: string, categorySlug: string): string {
  return `/brand/${brandSlug}/${categorySlug}`;
}

export function getBrandBySlug(slug: string): BrandMenuItem | undefined {
  return buyFromTzBrandMenu.find((brand) => brand.slug === slug);
}

export function getBrandCategory(
  brandSlug: string,
  categorySlug: string,
): BrandCategory | undefined {
  const brand = getBrandBySlug(brandSlug);
  return brand?.subcategories.find((category) => category.slug === categorySlug);
}
