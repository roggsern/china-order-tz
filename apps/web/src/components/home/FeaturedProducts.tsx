import Link from "next/link";
import { getFeaturedProducts } from "@/lib/catalog/products";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ArrowRightIcon } from "./icons";

export function FeaturedProducts() {
  const products = getFeaturedProducts(8);

  return (
    <section id="products" className="bg-white py-20 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
          <div className="text-center sm:text-left">
            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
              Hot Deals
            </p>
            <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
              Featured Products
            </h2>
            <p className="mx-auto mt-3 max-w-2xl text-base text-zinc-500 sm:mx-0">
              Hand-picked premium products with unbeatable factory-direct prices — updated daily
              from our supplier network.
            </p>
          </div>
          <Link
            href="/products"
            className="inline-flex shrink-0 items-center gap-1 rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
          >
            View all
            <ArrowRightIcon className="h-4 w-4" />
          </Link>
        </div>

        <div className="mt-12">
          <ProductGrid products={products} />
        </div>
      </div>
    </section>
  );
}
