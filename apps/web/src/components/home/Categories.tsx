import Link from "next/link";
import { categories } from "@/lib/home-data";
import { getProducts } from "@/lib/catalog/products";
import { ArrowRightIcon } from "./icons";

function PremiumCategoryCard({
  category,
  productCount,
}: {
  category: (typeof categories)[number];
  productCount: number;
}) {
  return (
    <Link
      href={`/products?category=${category.slug}`}
      className="premium-card group relative flex min-h-[320px] flex-col overflow-hidden rounded-3xl bg-zinc-900 shadow-[0_8px_32px_rgba(0,0,0,0.12)] transition duration-500 hover:-translate-y-1.5 hover:shadow-[0_20px_48px_rgba(0,0,0,0.18)] sm:min-h-[360px]"
    >
      <div
        className={`absolute inset-0 bg-gradient-to-br ${category.gradient} opacity-90 transition duration-500 group-hover:opacity-100 group-hover:scale-105`}
      />
      <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-black/5" />

      <div className="relative flex flex-1 flex-col justify-between p-6 sm:p-7">
        <div className="flex items-start justify-between gap-4">
          <span className="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-3xl backdrop-blur-md">
            {category.icon}
          </span>
          <span className="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/80 backdrop-blur-sm">
            {productCount}+ items
          </span>
        </div>

        <div>
          <h3 className="text-xl font-bold tracking-tight text-white sm:text-2xl">
            {category.name}
          </h3>
          <p className="mt-2 max-w-[90%] text-sm leading-relaxed text-white/70">
            {category.description}
          </p>
          <span className="mt-5 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-[#e8c547] opacity-0 transition duration-300 group-hover:opacity-100">
            Explore collection
            <ArrowRightIcon className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
          </span>
        </div>
      </div>

      <div className="pointer-events-none absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[#c9a227] to-transparent opacity-0 transition duration-500 group-hover:opacity-100" />
    </Link>
  );
}

export async function Categories() {
  const catalog = await getProducts();
  const featuredCategories = categories.slice(0, 8);

  return (
    <section id="categories" className="relative overflow-hidden bg-white py-20 sm:py-28">
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute -left-32 top-0 h-96 w-96 rounded-full bg-[#c9a227]/5 blur-3xl" />
        <div className="absolute -right-32 bottom-0 h-96 w-96 rounded-full bg-zinc-100 blur-3xl" />
      </div>

      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
          <div className="max-w-xl">
            <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
              Collections
            </p>
            <h2 className="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl lg:text-[2.75rem]">
              Shop by Category
            </h2>
            <p className="mt-4 text-base leading-relaxed text-zinc-500">
              Curated collections sourced from China&apos;s leading manufacturing hubs — delivered
              to your door in Tanzania.
            </p>
          </div>
          <Link
            href="/categories"
            className="inline-flex shrink-0 items-center gap-2 rounded-full border border-zinc-200 bg-white px-5 py-2.5 text-sm font-semibold text-zinc-900 shadow-sm transition hover:border-[#c9a227]/40 hover:text-[#8b6914]"
          >
            View all
            <ArrowRightIcon className="h-4 w-4" />
          </Link>
        </div>

        <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {featuredCategories.map((category) => (
            <PremiumCategoryCard
              key={category.slug}
              category={category}
              productCount={catalog.filter((product) => product.categorySlug === category.slug).length}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
