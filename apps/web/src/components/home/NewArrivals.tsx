import Link from "next/link";
import {
  getHomeNewArrivals,
  HOMEPAGE_EMPTY_PRODUCTS_MESSAGE,
} from "@/lib/catalog/home-catalog";
import { CatalogApiError } from "@/lib/catalog/products";
import { ProductCard } from "@/components/catalog/ProductCard";
import { ArrowRightIcon } from "./icons";
import { CatalogErrorState } from "@/components/catalog/CatalogErrorState";

export async function NewArrivals() {
  try {
    const products = await getHomeNewArrivals(8);

    return (
      <section id="new-arrivals" className="relative overflow-hidden bg-white py-20 sm:py-28">
        <div className="pointer-events-none absolute inset-0">
          <div className="absolute left-0 top-0 h-80 w-80 rounded-full bg-[#c9a227]/5 blur-3xl" />
        </div>

        <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
            <div className="max-w-xl">
              <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
                Just In
              </p>
              <h2 className="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl lg:text-[2.75rem]">
                New Arrivals
              </h2>
              <p className="mt-4 text-base leading-relaxed text-zinc-500">
                Fresh additions from our supplier network — discover the latest products available
                for import to Tanzania.
              </p>
            </div>
            <Link
              href="/products?sort=newest"
              className="inline-flex shrink-0 items-center gap-2 rounded-full bg-zinc-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
            >
              View all
              <ArrowRightIcon className="h-4 w-4" />
            </Link>
          </div>

          {products.length === 0 ? (
            <div className="mt-10 rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 py-16 text-center text-sm text-zinc-500">
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
        : "Something went wrong while loading new arrivals.";

    return (
      <section id="new-arrivals" className="bg-white py-20 sm:py-28">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <CatalogErrorState title="New arrivals unavailable" message={message} />
        </div>
      </section>
    );
  }
}
