import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductCard } from "@/components/catalog/ProductCard";
import { getTzStore, getTzStoreCategories, getTzStoreProducts } from "@/lib/api/tz-stores";
import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";

interface CategoryPageProps {
  params: Promise<{ storeSlug: string; categorySlug: string }>;
}

export async function generateMetadata({ params }: CategoryPageProps): Promise<Metadata> {
  const { storeSlug, categorySlug } = await params;
  try {
    const [store, categories] = await Promise.all([
      getTzStore(storeSlug),
      getTzStoreCategories(storeSlug),
    ]);
    const category = categories.find((item) => item.slug === categorySlug);
    return {
      title: category
        ? `${category.name} — ${store.name} — CHINA ORDER TZ`
        : `${store.name} — Buy From TZ`,
    };
  } catch {
    return { title: "Category — Buy From TZ" };
  }
}

export default async function BuyFromTzCategoryPage({ params }: CategoryPageProps) {
  const { storeSlug, categorySlug } = await params;

  let store;
  try {
    store = await getTzStore(storeSlug);
  } catch {
    notFound();
  }

  const categories = await getTzStoreCategories(storeSlug).catch(() => []);
  const category = categories.find((item) => item.slug === categorySlug);
  if (!category) {
    notFound();
  }

  const productResult = await getTzStoreProducts(storeSlug, {
    category: categorySlug,
    per_page: 24,
  }).catch(() => ({
    products: [],
    meta: { current_page: 1, last_page: 1, per_page: 24, total: 0 },
  }));

  const products = productResult.products.map(mapApiProductCardToCatalogProduct);

  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs
          items={[
            { label: "Buy From TZ", href: "/buy-from-tz" },
            { label: store.name, href: `/buy-from-tz/${store.slug}` },
            { label: category.name },
          ]}
        />
        <div className="mt-6">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            {store.name}
          </p>
          <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900">
            {category.name}
          </h1>
        </div>

        <div className="mt-10">
          {products.length === 0 ? (
            <div className="rounded-2xl border border-zinc-200 bg-white p-10 text-center">
              <p className="text-sm text-zinc-500">No products in this category yet.</p>
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
