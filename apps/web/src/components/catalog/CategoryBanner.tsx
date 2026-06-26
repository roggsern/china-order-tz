import type { Category, Product } from "@/lib/types/catalog";

interface CategoryBannerProps {
  category: Category;
  productCount?: number;
  catalog?: Product[];
}

export function CategoryBanner({ category, productCount: productCountProp, catalog }: CategoryBannerProps) {
  const productCount =
    productCountProp ??
    catalog?.filter((product) => product.categorySlug === category.slug).length ??
    0;

  return (
    <div className="relative overflow-hidden rounded-3xl bg-zinc-900 shadow-[0_20px_60px_rgba(0,0,0,0.15)]">
      <div
        className={`absolute inset-0 bg-gradient-to-br ${category.gradient} opacity-40`}
      />
      <div className="absolute inset-0 bg-gradient-to-r from-zinc-950/90 via-zinc-900/70 to-transparent" />
      <div className="relative flex min-h-[220px] flex-col justify-center px-8 py-12 sm:min-h-[280px] sm:px-12 lg:px-16">
        <p className="text-sm font-semibold uppercase tracking-[0.25em] text-[#e8c547]">
          Category
        </p>
        <div className="mt-3 flex items-center gap-4">
          <span className="text-5xl drop-shadow-lg sm:text-6xl">{category.icon}</span>
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
              {category.name}
            </h1>
            <p className="mt-2 max-w-xl text-base text-zinc-300 sm:text-lg">
              {category.description}
            </p>
          </div>
        </div>
        <p className="mt-6 text-sm font-medium text-zinc-400">
          {productCount} premium product{productCount !== 1 ? "s" : ""} available
        </p>
      </div>
    </div>
  );
}
