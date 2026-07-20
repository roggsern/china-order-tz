import type { ApiCatalogCategory } from "@/lib/api/products";
import { getFeaturedForCategory } from "@/lib/catalog/categories";
import { enrichApiCategoryFromStatic } from "@/lib/catalog/category-presentation";
import type { Category } from "@/lib/types/catalog";

export type MegaMenuSubcategory = {
  name: string;
  slug: string;
  /** True when the node exists in the database taxonomy. */
  fromApi?: boolean;
};

export type MegaMenuCategory = Category & {
  subcategories: MegaMenuSubcategory[];
  featured: string;
  /** Category slug for parent-level CTAs. */
  shopSlug: string;
};

export function getMegaMenuSubcategoryHref(
  sub: MegaMenuSubcategory,
  shopSlug: string,
): string {
  if (sub.fromApi) {
    return `/products?category=${sub.slug}`;
  }

  if (shopSlug) {
    return `/products?category=${shopSlug}&q=${encodeURIComponent(sub.name)}`;
  }

  return `/products?q=${encodeURIComponent(sub.name)}`;
}

/**
 * Build mega menu from the database taxonomy tree only.
 * Does not invent categories or subcategories.
 */
export function buildMegaMenuCategories(
  apiCategories: ApiCatalogCategory[],
): MegaMenuCategory[] {
  return apiCategories
    .filter((node) => !node.parent_id)
    .map((node) => {
      const children = node.children ?? [];
      const presentation = enrichApiCategoryFromStatic({
        slug: node.slug,
        name: node.name,
      });

      const subcategories: MegaMenuSubcategory[] = children.map((child) => ({
        name: child.name,
        slug: child.slug,
        fromApi: true,
      }));

      return {
        ...presentation,
        id: node.id,
        slug: node.slug,
        name: node.name,
        subcategories,
        featured: getFeaturedForCategory(node.slug),
        shopSlug: children[0]?.slug ?? node.slug,
      };
    });
}
