import Link from "next/link";
import { categories } from "@/lib/home-data";
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
            href="#products"
            className="inline-flex items-center gap-1 text-sm font-semibold text-zinc-900 transition hover:text-[#c9a227]"
          >
            View all products
            <ArrowRightIcon className="h-4 w-4" />
          </Link>
        </div>

        <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {categories.map((category, index) => (
            <Link
              key={category.slug}
              href="#products"
              className="group relative overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200/80 transition hover:-translate-y-1 hover:shadow-xl hover:ring-[#c9a227]/30"
              style={{ animationDelay: `${index * 50}ms` }}
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
                <span className="mt-4 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-[#c9a227] opacity-0 transition group-hover:opacity-100">
                  Shop now
                  <ArrowRightIcon className="h-3 w-3" />
                </span>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
