import Link from "next/link";
import type { Category } from "@/lib/types/catalog";
import { getProductsByCategory } from "@/lib/catalog/products";
import { ArrowRightIcon } from "@/components/home/icons";

interface CategoryCardProps {
  category: Category;
  showProductCount?: boolean;
}

export function CategoryCard({ category, showProductCount = true }: CategoryCardProps) {
  const productCount = getProductsByCategory(category.slug).length;

  return (
    <Link
      href={`/products?category=${category.slug}`}
      className="group relative overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200/80 transition hover:-translate-y-1 hover:shadow-xl hover:ring-[#c9a227]/30"
    >
      <div
        className={`relative flex h-40 items-center justify-center bg-gradient-to-br ${category.gradient}`}
      >
        <span className="text-5xl drop-shadow-lg transition group-hover:scale-110">
          {category.icon}
        </span>
        <div className="absolute inset-0 bg-black/0 transition group-hover:bg-black/10" />
      </div>
      <div className="p-5">
        <h3 className="text-base font-bold text-zinc-900 group-hover:text-[#8b6914]">
          {category.name}
        </h3>
        <p className="mt-1.5 text-sm leading-snug text-zinc-500">{category.description}</p>
        {showProductCount && (
          <p className="mt-2 text-xs font-medium text-zinc-400">
            {productCount} product{productCount !== 1 ? "s" : ""}
          </p>
        )}
        <span className="mt-4 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-[#c9a227] opacity-0 transition group-hover:opacity-100">
          Shop now
          <ArrowRightIcon className="h-3 w-3" />
        </span>
      </div>
    </Link>
  );
}
