import Link from "next/link";
import { categories } from "@/lib/home-data";
import { CategoryCard } from "@/components/catalog/CategoryCard";
import { ArrowRightIcon } from "./icons";

export function Categories() {
  return (
    <section id="categories" className="bg-zinc-50 py-20 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
              Shop by Category
            </p>
            <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
              Explore Top Categories
            </h2>
            <p className="mt-3 max-w-xl text-base text-zinc-500">
              From fashion and furniture to building materials — browse curated collections
              sourced directly from China&apos;s top manufacturing hubs.
            </p>
          </div>
          <Link
            href="/categories"
            className="inline-flex items-center gap-1 text-sm font-semibold text-zinc-900 transition hover:text-[#c9a227]"
          >
            View all categories
            <ArrowRightIcon className="h-4 w-4" />
          </Link>
        </div>

        <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {categories.map((category) => (
            <CategoryCard key={category.slug} category={category} showProductCount={false} />
          ))}
        </div>
      </div>
    </section>
  );
}
