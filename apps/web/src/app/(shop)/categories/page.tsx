import type { Metadata } from "next";
import { categories } from "@/lib/catalog/categories";
import { CategoryCard } from "@/components/catalog/CategoryCard";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";

export const metadata: Metadata = {
  title: "Categories — CHINA ORDER TZ",
  description: "Browse all product categories — fashion, electronics, beauty, furniture, and more.",
};

export default function CategoriesPage() {
  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs items={[{ label: "Categories" }]} />

        <div className="mt-6">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            Shop by Category
          </p>
          <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            All Categories
          </h1>
          <p className="mt-3 max-w-2xl text-base text-zinc-500">
            From fashion and furniture to building materials — browse curated collections sourced
            directly from China&apos;s top manufacturing hubs.
          </p>
        </div>

        <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {categories.map((category) => (
            <CategoryCard key={category.slug} category={category} />
          ))}
        </div>
      </div>
    </div>
  );
}
