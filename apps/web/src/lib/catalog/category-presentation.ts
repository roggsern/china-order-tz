import { categories } from "@/lib/catalog/category-seed";
import type { Category } from "@/lib/types/catalog";

const CATEGORY_PRESENTATION: Record<
  string,
  Pick<Category, "description" | "gradient" | "icon">
> = Object.fromEntries(
  categories.map((category) => [
    category.slug,
    {
      description: category.description,
      gradient: category.gradient,
      icon: category.icon,
    },
  ]),
);

/**
 * Optional presentation enrichment (icons/gradients only).
 * Hierarchy itself always comes from the database.
 */
export function enrichApiCategoryFromStatic(category: { slug: string; name: string }): Category {
  const presentation = CATEGORY_PRESENTATION[category.slug];

  return {
    slug: category.slug,
    name: category.name,
    description: presentation?.description ?? `Shop ${category.name} with CHINA ORDER TZ.`,
    gradient: presentation?.gradient ?? "from-zinc-700 via-zinc-800 to-zinc-900",
    icon: presentation?.icon ?? "🛍️",
  };
}
