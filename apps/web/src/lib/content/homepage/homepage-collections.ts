import type { ApiCatalogCategory } from "@/lib/api/products";
import { getChinaStorefrontFeaturedCollections } from "@/lib/api/china-storefront";
import { enrichApiCategoryFromStatic } from "@/lib/catalog/category-presentation";
import type { HomepageCollection } from "./types";

export function mapChinaCategoryToHomepageCollection(
  category: ApiCatalogCategory,
): HomepageCollection {
  const presentation = enrichApiCategoryFromStatic({
    slug: category.slug,
    name: category.name,
  });

  return {
    id: category.id,
    name: category.name,
    slug: category.slug,
    description: presentation.description,
    href: `/products?origin=china&category=${encodeURIComponent(category.slug)}`,
    icon: presentation.icon,
    gradient: presentation.gradient,
  };
}

export function mapChinaCategoriesToHomepageCollections(
  categories: ApiCatalogCategory[],
): HomepageCollection[] {
  return categories.map(mapChinaCategoryToHomepageCollection);
}

/**
 * Priority: CMS configured collections → live China catalog → empty (section hidden).
 */
export function resolveHomepageFeaturedCollections(
  cmsCollections: HomepageCollection[] | undefined,
  catalogCollections: HomepageCollection[],
): HomepageCollection[] {
  if (cmsCollections && cmsCollections.length > 0) {
    return cmsCollections;
  }

  return catalogCollections;
}

export async function fetchHomepageFeaturedCollectionsFromCatalog(): Promise<
  HomepageCollection[]
> {
  const categories = await getChinaStorefrontFeaturedCollections();
  return mapChinaCategoriesToHomepageCollections(categories);
}
