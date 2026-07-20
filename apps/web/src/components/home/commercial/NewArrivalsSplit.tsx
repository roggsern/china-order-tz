import Link from "next/link";
import type { HomepageSectionCopy } from "@/lib/content/homepage";
import type { Product } from "@/lib/types/catalog";
import { ProductCard } from "@/components/catalog/ProductCard";
import { CountryFlag } from "@/components/storefront/CountryFlag";
import { STOREFRONT_NAV_LABELS } from "@/lib/storefront/navigation-policy";
import { ArrowRightIcon } from "../icons";

type NewArrivalsSplitProps = {
  chinaProducts: Product[];
  tzProducts: Product[];
  copy: HomepageSectionCopy;
};

function JourneyGrid({
  title,
  flag,
  href,
  products,
}: {
  title: string;
  flag: "CN" | "TZ";
  href: string;
  products: Product[];
}) {
  return (
    <div>
      <div className="mb-4 flex items-center justify-between gap-3">
        <h3 className="inline-flex items-center gap-2 text-lg font-bold text-zinc-900">
          <CountryFlag country={flag} size={18} decorative />
          {title}
        </h3>
        <Link
          href={href}
          className="inline-flex items-center gap-1 text-xs font-semibold text-zinc-600 transition hover:text-[#c9a227]"
        >
          View all
          <ArrowRightIcon className="h-3.5 w-3.5" />
        </Link>
      </div>
      {products.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 py-12 text-center text-sm text-zinc-500">
          No new products in this journey yet.
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-2 xl:grid-cols-2">
          {products.slice(0, 4).map((product) => (
            <ProductCard key={product.id} product={product} variant="luxury" />
          ))}
        </div>
      )}
    </div>
  );
}

export function NewArrivalsSplit({
  chinaProducts,
  tzProducts,
  copy,
}: NewArrivalsSplitProps) {
  return (
    <section id="new-arrivals" className="bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
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

        <div className="mt-10 grid gap-10 lg:grid-cols-2 lg:gap-8">
          <JourneyGrid
            title={STOREFRONT_NAV_LABELS.orderFromChina}
            flag="CN"
            href="/products?origin=china"
            products={chinaProducts}
          />
          <JourneyGrid
            title={STOREFRONT_NAV_LABELS.buyFromTz}
            flag="TZ"
            href="/buy-from-tz"
            products={tzProducts}
          />
        </div>
      </div>
    </section>
  );
}
