import Link from "next/link";
import {
  getHomeFeaturedProducts,
  HOMEPAGE_EMPTY_PRODUCTS_MESSAGE,
} from "@/lib/catalog/home-catalog";
import { CatalogApiError } from "@/lib/catalog/products";
import type { HomepageSectionCopy } from "@/lib/content/homepage";
import type { Product } from "@/lib/types/catalog";
import { ProductCard } from "@/components/catalog/ProductCard";
import { ArrowRightIcon } from "./icons";
import { CatalogErrorState } from "@/components/catalog/CatalogErrorState";

type FeaturedProductsProps = {
  /** When provided (e.g. from CMS), skips catalog featured fetch. */
  products?: Product[];
  copy?: HomepageSectionCopy;
};

const DEFAULT_COPY: HomepageSectionCopy = {
  eyebrow: "Curated Selection",
  title: "Featured Products",
  description:
    "Hand-picked premium products with factory-direct pricing — updated daily from our verified supplier network.",
  viewAllLabel: "View all",
  viewAllHref: "/products",
};

export async function FeaturedProducts({ products: productsProp, copy }: FeaturedProductsProps = {}) {
  const resolvedCopy = copy ?? DEFAULT_COPY;

  try {
    const products =
      productsProp !== undefined
        ? productsProp
        : await getHomeFeaturedProducts(8);

    return (
      <section id="products" className="relative overflow-hidden bg-zinc-50 py-20 sm:py-28">
        <div className="pointer-events-none absolute inset-0">
          <div className="absolute right-0 top-0 h-80 w-80 rounded-full bg-[#c9a227]/5 blur-3xl" />
        </div>

        <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
            <div className="max-w-xl">
              <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
                {resolvedCopy.eyebrow}
              </p>
              <h2 className="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl lg:text-[2.75rem]">
                {resolvedCopy.title}
              </h2>
              <p className="mt-4 text-base leading-relaxed text-zinc-500">
                {resolvedCopy.description}
              </p>
            </div>
            <Link
              href={resolvedCopy.viewAllHref ?? "/products"}
              className="inline-flex shrink-0 items-center gap-2 rounded-full bg-zinc-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
            >
              {resolvedCopy.viewAllLabel ?? "View all"}
              <ArrowRightIcon className="h-4 w-4" />
            </Link>
          </div>

          {products.length === 0 ? (
            <div className="mt-10 rounded-2xl border border-dashed border-zinc-200 bg-white py-16 text-center text-sm text-zinc-500">
              {HOMEPAGE_EMPTY_PRODUCTS_MESSAGE}
            </div>
          ) : (
            <div className="mt-10 grid grid-cols-2 gap-3 sm:mt-14 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
              {products.map((product) => (
                <ProductCard key={product.id} product={product} variant="luxury" />
              ))}
            </div>
          )}
        </div>
      </section>
    );
  } catch (error) {
    const message =
      error instanceof CatalogApiError
        ? error.message
        : "Something went wrong while loading featured products.";

    return (
      <section id="products" className="bg-zinc-50 py-20 sm:py-28">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <CatalogErrorState title="Featured products unavailable" message={message} />
        </div>
      </section>
    );
  }
}
