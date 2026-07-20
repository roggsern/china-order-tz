import Image from "next/image";
import Link from "next/link";
import type { HomepageSectionCopy } from "@/lib/content/homepage";
import type { TzStorefrontStore } from "@/lib/api/tz-stores";
import { ArrowRightIcon } from "../icons";

type ShopByStoreProps = {
  stores: TzStorefrontStore[];
  copy: HomepageSectionCopy;
};

function StoreLogo({ store }: { store: TzStorefrontStore }) {
  if (store.logo_url) {
    return (
      <Image
        src={store.logo_url}
        alt=""
        width={56}
        height={56}
        className="h-14 w-14 rounded-xl object-cover"
        unoptimized
      />
    );
  }

  return (
    <span
      className="flex h-14 w-14 items-center justify-center rounded-xl text-lg font-bold text-white"
      style={{ backgroundColor: store.theme_color || "#c9a227" }}
      aria-hidden
    >
      {store.name.trim().charAt(0) || "S"}
    </span>
  );
}

export function ShopByStore({ stores, copy }: ShopByStoreProps) {
  if (stores.length === 0) {
    return null;
  }

  return (
    <section id="shop-by-store" className="bg-zinc-50 py-16 sm:py-20">
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
              {copy.viewAllLabel || "All stores"}
              <ArrowRightIcon className="h-4 w-4" />
            </Link>
          ) : null}
        </div>

        <ul className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {stores.map((store) => (
            <li key={store.id}>
              <article className="flex h-full flex-col overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div
                  className="relative h-28 w-full bg-gradient-to-br from-zinc-100 to-zinc-200"
                  style={
                    store.theme_color
                      ? {
                          background: `linear-gradient(135deg, ${store.theme_color}33, #f4f4f5)`,
                        }
                      : undefined
                  }
                >
                  {store.banner_url ? (
                    <Image
                      src={store.banner_url}
                      alt=""
                      fill
                      className="object-cover"
                      sizes="(max-width:768px) 100vw, 25vw"
                      unoptimized
                    />
                  ) : null}
                </div>
                <div className="flex flex-1 flex-col p-5">
                  <div className="flex items-start gap-3">
                    <StoreLogo store={store} />
                    <div className="min-w-0">
                      <h3 className="truncate text-base font-bold text-zinc-900">{store.name}</h3>
                      <p className="mt-1 line-clamp-2 text-sm text-zinc-500">
                        {store.description || "Tanzanian storefront on CHINA ORDER TZ."}
                      </p>
                    </div>
                  </div>
                  <Link
                    href={`/buy-from-tz/${store.slug}`}
                    className="mt-5 inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-zinc-200 bg-zinc-50 px-4 text-xs font-semibold text-zinc-900 transition hover:border-[#c9a227]/40 hover:bg-[#c9a227]/10"
                  >
                    Visit store
                    <ArrowRightIcon className="h-3.5 w-3.5" />
                  </Link>
                </div>
              </article>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
