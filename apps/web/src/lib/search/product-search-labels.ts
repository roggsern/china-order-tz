import {
  formatBrandDisplayName,
  getBrandBySlug,
  getBrandCategory,
} from "@/lib/catalog/brands";
import { getCategoryBySlug, getSubcategories } from "@/lib/catalog/categories";
import { resolveProductType } from "@/lib/catalog/product-type";
import { slugify } from "@/lib/catalog/utils";
import type { Product } from "@/lib/types/catalog";

export type ProductSearchLabels = {
  categoryLabel: string;
  subcategoryLabel: string;
};

export function resolveProductSearchLabels(product: Product): ProductSearchLabels {
  const isLocal = resolveProductType(product) === "local";

  if (isLocal) {
    const brand = getBrandBySlug(product.categorySlug);
    const subcategory = product.subcategorySlug
      ? getBrandCategory(product.categorySlug, product.subcategorySlug)
      : undefined;

    return {
      categoryLabel: brand ? formatBrandDisplayName(brand.name) : product.brand ?? "",
      subcategoryLabel: subcategory?.name ?? "",
    };
  }

  const category = getCategoryBySlug(product.categorySlug);
  const categoryLabel = category?.name ?? product.categorySlug.replace(/-/g, " ");

  let subcategoryLabel = "";
  if (product.subcategorySlug) {
    const matched = getSubcategories(product.categorySlug).find(
      (entry) => slugify(entry) === product.subcategorySlug,
    );
    subcategoryLabel = matched ?? product.subcategorySlug.replace(/-/g, " ");
  }

  return { categoryLabel, subcategoryLabel };
}
