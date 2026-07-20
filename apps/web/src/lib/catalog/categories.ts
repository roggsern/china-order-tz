import type { Category } from "@/lib/types/catalog";
import { getCategories as fetchApiCategories } from "@/lib/api/products";
import { enrichApiCategoryFromStatic } from "@/lib/catalog/category-presentation";
import { categories } from "@/lib/catalog/category-seed";

/** Presentation-only seed (icons/gradients). Not the taxonomy source of truth. */
export { categories };

/** Subcategories come from the database — do not invent placeholder lists. */
export function getSubcategories(_slug: string): readonly string[] {
  return [];
}

export function getFeaturedForCategory(slug: string): string {
  const map: Record<string, string> = {
    "womens-fashion": "Summer Collection 2026",
    "mens-fashion": "Business Essentials",
    electronics: "Smart Gadgets Sale",
    beauty: "K-Beauty Favorites",
    furniture: "Modern Living Sets",
    "building-materials": "Bulk Tile Deals",
    "home-kitchen": "Kitchen Upgrade Picks",
    "kids-baby": "Back to School",
  };
  return map[slug] ?? "Featured Picks";
}

/** @deprecated Prefer database taxonomy via getCatalogCategories(). */
export const megaMenuCategories = categories.map((category) => ({
  ...category,
  subcategories: getSubcategories(category.slug),
  featured: getFeaturedForCategory(category.slug),
}));

export function getCategoryBySlug(slug: string): Category | undefined {
  return categories.find((c) => c.slug === slug);
}

export async function getCatalogCategories(): Promise<Category[]> {
  const apiCategories = await fetchApiCategories({ tree: true });
  return apiCategories.map(enrichApiCategoryFromStatic);
}

export async function resolveCategoryBySlug(slug: string): Promise<Category | undefined> {
  const catalogCategories = await getCatalogCategories();
  return catalogCategories.find((category) => category.slug === slug);
}
