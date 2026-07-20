import Link from "next/link";
import type { HomepageSectionCopy } from "@/lib/content/homepage";
import type { Product } from "@/lib/types/catalog";
import { ProductCard } from "@/components/catalog/ProductCard";
import { ArrowRightIcon } from "../icons";

type BestSellersProps = {
  products: Product[];
  copy: HomepageSectionCopy;
};

export function BestSellers({ products, copy }: BestSellersProps) {
  return (
    <section id="best-sellers" className="bg-zinc-50 py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
          <div className="max-w-xl">
            <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
              {copy.eyebrow}
            </p>
            <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
              {copy.title}
            </h2>
            <p className="mt-3 text-sm leading-relaxed text-zinc-500 sm:text-base">
              {copy.description}
            </p>
          </div>
          {copy.viewAllHref ? (
            <Link
              href={copy.viewAllHref}
              className="inline-flex items-center gap-2 rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
            >
              {copy.viewAllLabel || "View all"}
              <ArrowRightIcon className="h-4 w-4" />
            </Link>
          ) : null}
        </div>

        {products.length === 0 ? (
          <div className="mt-10 rounded-2xl border border-dashed border-zinc-200 bg-white py-16 text-center text-sm text-zinc-500">
            Bestsellers will appear as purchase data grows.
          </div>
        ) : (
          <div className="mt-10 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} variant="luxury" />
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
