import type { Metadata } from "next";
import Link from "next/link";
import { categories } from "@/lib/catalog/categories";
import { getProducts } from "@/lib/catalog/products";
import { CategoryCard } from "@/components/catalog/CategoryCard";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ArrowRightIcon } from "@/components/home/icons";

export const metadata: Metadata = {
  title: "Categories — CHINA ORDER TZ",
  description: "Browse all product categories — fashion, electronics, beauty, furniture, and more.",
};

export default async function CategoriesPage() {
  const catalog = await getProducts();

  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs items={[{ label: "Categories" }]} />

        <div className="relative mt-6 overflow-hidden rounded-3xl bg-zinc-900 px-8 py-14 sm:px-12 sm:py-16">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(201,162,39,0.15),transparent_60%)]" />
          <div className="relative max-w-2xl">
            <p className="text-sm font-semibold uppercase tracking-[0.25em] text-[#e8c547]">
              Shop by Category
            </p>
            <h1 className="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
              All Categories
            </h1>
            <p className="mt-4 text-base leading-relaxed text-zinc-300 sm:text-lg">
              From fashion and furniture to building materials — browse curated collections with
              premium spacing, smart delivery estimates, and verified suppliers.
            </p>
            <p className="mt-6 text-sm font-medium text-zinc-400">
              {categories.length} categories · {catalog.length} products
            </p>
          </div>
        </div>

        <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {categories.map((category) => (
            <CategoryCard key={category.slug} category={category} catalog={catalog} />
          ))}
        </div>

        <div className="mt-16 flex justify-center">
          <Link
            href="/products"
            className="inline-flex items-center gap-2 rounded-full bg-zinc-900 px-8 py-3.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
          >
            Browse All Products
            <ArrowRightIcon className="h-4 w-4" />
          </Link>
        </div>
      </div>
    </div>
  );
}
