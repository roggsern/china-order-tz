import type { Metadata } from "next";
import Link from "next/link";
import Image from "next/image";
import { notFound } from "next/navigation";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductCard } from "@/components/catalog/ProductCard";
import { getTzStore, getTzStoreCategories, getTzStoreProducts } from "@/lib/api/tz-stores";
import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";

interface StorePageProps {
  params: Promise<{ storeSlug: string }>;
}

export async function generateMetadata({ params }: StorePageProps): Promise<Metadata> {
  const { storeSlug } = await params;
  try {
    const store = await getTzStore(storeSlug);
    return {
      title: `${store.name} — Buy From TZ — CHINA ORDER TZ`,
      description: store.description ?? `Shop ${store.name} on China Order TZ.`,
    };
  } catch {
    return { title: "Store — Buy From TZ" };
  }
}

export default async function BuyFromTzStorePage({ params }: StorePageProps) {
  const { storeSlug } = await params;

  let store;
  try {
    store = await getTzStore(storeSlug);
  } catch {
    notFound();
  }

  const [categories, productResult] = await Promise.all([
    getTzStoreCategories(storeSlug).catch(() => []),
    getTzStoreProducts(storeSlug, { per_page: 24 }).catch(() => ({
      products: [],
      meta: { current_page: 1, last_page: 1, per_page: 24, total: 0 },
    })),
  ]);

  const products = productResult.products.map(mapApiProductCardToCatalogProduct);

  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs
          items={[
            { label: "Buy From TZ", href: "/buy-from-tz" },
            { label: store.name },
          ]}
        />

        <div className="mt-6 flex flex-wrap items-start gap-5">
          <div className="relative h-20 w-20 overflow-hidden rounded-2xl ring-1 ring-zinc-200">
            {store.logo_url ? (
              <Image
                src={store.logo_url}
                alt={`${store.name} logo`}
                fill
                className="object-cover"
                unoptimized
              />
            ) : (
              <span
                className="flex h-full w-full items-center justify-center text-2xl font-bold text-white"
                style={{ backgroundColor: store.theme_color || "#c9a227" }}
              >
                {store.name.charAt(0)}
              </span>
            )}
          </div>
          <div className="min-w-0 flex-1">
            <h1 className="text-3xl font-bold tracking-tight text-zinc-900">{store.name}</h1>
            <p className="mt-2 max-w-2xl text-base text-zinc-500">
              {store.description || "Local Tanzanian store"}
            </p>
          </div>
        </div>

        {categories.length > 0 ? (
          <div className="mt-8 flex flex-wrap gap-2">
            {categories.map((category) => (
              <Link
                key={category.id}
                href={`/buy-from-tz/${store.slug}/category/${category.slug}`}
                className="rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-700 transition hover:border-[#c9a227]/50 hover:text-[#c9a227]"
              >
                {category.name}
              </Link>
            ))}
          </div>
        ) : null}

        <div className="mt-10">
          {products.length === 0 ? (
            <div className="rounded-2xl border border-zinc-200 bg-white p-10 text-center">
              <p className="text-sm text-zinc-500">
                No products are available in this store yet. Check back soon.
              </p>
            </div>
          ) : (
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {products.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
